/**
 * Copyright ETS Software Technology Co., Ltd
 *
 * NOTICE OF LICENSE
 *
 * This file is not open source! Each license that you purchased is only available for 1 website only.
 * If you want to use this file on more websites (or projects), you need to purchase additional licenses.
 * You are not allowed to redistribute, resell, lease, license, sub-license or offer our resources to any third party.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future.
 *
 * @author ETS Software Technology Co., Ltd
 * @copyright  ETS Software Technology Co., Ltd
 * @license    Valid for 1 website (or project) for each purchase of license
 */

var ETS_EM_XHR_POOL = [],
    ETS_EM_PROCESS_STOP = false,
    ETS_EM_TIMER = 0,
    ETS_EM_DATA_INFO_SOURCE = ETS_EM_DATA_INFO_SOURCE || [],
    ETS_EM_MIGRATE_RESOURCE = 'minor_data',
    ETS_EM_CURRENT_TASK = '',
    ETS_EM_RETRY = 0,
    ETS_EM_MAX_RETRY = 5,
    ETS_EM_RETRY_DELAY = 2000
;
var ETS_EM_MIGRATE_GROUP_ORDERS = [
    'orders',
    'customer',
    'carrier',
];
var ETS_EM_MIGRATE_GROUP_PRODUCT = [
    'category',
    'product',
];
var ets_em_copied_translate = ets_em_copied_translate || 'Copied',
    ets_em_do_not_import = ets_em_do_not_import || '--- Do not import ---',
    ets_em_create_shop = ets_em_create_shop || '--- Create shop ---',
    ets_em_mapping_shop_invalid = ets_em_mapping_shop_invalid || 'Shop mapping is invalid. Please select target shop or create new shop.',
    ets_em_migrate = ets_em_migrate || 'Migrate',
    ets_em_migrate_now = ets_em_migrate_now || 'Migrate now',
    ets_em_new_migration = ets_em_new_migration || 'Start a new migration? Current progress will be cleared.',
    ets_em_migrate_data_empty = ets_em_migrate_data_empty || 'Select at least one data type to migrate.',
    ets_em_leave_migration = ets_em_leave_migration || 'A migration is in progress. Leaving this page may interrupt the process.',
    ets_em_migration_queue_empty = typeof ets_em_migration_queue_empty !== 'undefined' ? ets_em_migration_queue_empty : 'Migration queue is empty. Click Play to retry or start a New migration.',
    ets_em_ajax_error = typeof ets_em_ajax_error !== 'undefined' ? ets_em_ajax_error : 'An unexpected error occurred. Please try again.';
;
var ETS_EM_BEFORE_UNLOAD_BOUND = false;
var etsMigrateAdmin = {
    navigateWizardSteps: function (stepNav) {
        if (stepNav.length > 0) {
            $('.ets-em-nav-step.active, .form-wrapper-group-step.active, .form_header_block, .panel-form-footer').removeClass('active');
            var go_step = stepNav.data('step');
            stepNav.addClass('active');
            $('.form-wrapper-group-step.step' + go_step).addClass('active');
            $('.form_header_block.step' + go_step).addClass('active');
            $('.panel-form-footer.step' + go_step).addClass('active');
            $('#current_step').val(go_step);
        }
        etsMigrateAdmin.updateMigrationBeforeUnload();
    },
    bindMigrationBeforeUnload: function () {
        if (ETS_EM_BEFORE_UNLOAD_BOUND) {
            return;
        }
        ETS_EM_BEFORE_UNLOAD_BOUND = true;
        $(window).on('beforeunload.etsEmMigrate', function (e) {
            e.returnValue = ets_em_leave_migration;
            return ets_em_leave_migration;
        });
    },
    unbindMigrationBeforeUnload: function () {
        if (!ETS_EM_BEFORE_UNLOAD_BOUND) {
            return;
        }
        ETS_EM_BEFORE_UNLOAD_BOUND = false;
        $(window).off('beforeunload.etsEmMigrate');
    },
    updateMigrationBeforeUnload: function () {
        var step3Active = $('li.ets-em-nav-step.step3.active').length > 0 || parseInt($('#current_step').val(), 10) === 3;
        if (step3Active && !ETS_EM_PROCESS_STOP && ETS_EM_MIGRATE_RESOURCE !== 'finished') {
            etsMigrateAdmin.bindMigrationBeforeUnload();
        } else {
            etsMigrateAdmin.unbindMigrationBeforeUnload();
        }
    },
    isSafeMigratingName: function (name) {
        return typeof name === 'string' && /^[a-zA-Z0-9_]+$/.test(name);
    },
    displayTask: function (json) {
        if ($('.ets_em_process_item').length > 0) {
            if (!etsMigrateAdmin.isSafeMigratingName(json.migrating)) {
                return;
            }
            if (json.task_complete) {
                $('.ets_em_process_item_content.' + json.migrating).removeClass('item_processing').removeClass('item_waiting').addClass('item_success');
                $('.ets_em_process_item_icon.' + json.migrating + ' span').removeClass('dot-flashing').addClass('icon_success');
                $('.ets_em_process_item_percent.' + json.migrating).html('100%');
                $('.ets_em_process_item_running.' + json.migrating).css('width', '100%');
                if ($('.ets_em_process_item.' + json.migrating + ':not(:last-child)').length > 0) {
                    var task = $('.ets_em_process_item.' + json.migrating).next();
                    if (task.length > 0) {
                        etsMigrateAdmin.updateTaskProgress({migrating: task.data('task'), percent: 0});
                    }
                }
            } else if (json.migrating) {
                etsMigrateAdmin.updateTaskProgress(json);
            }
            var countItem = $('.ets_em_process_item_files.' + json.migrating);
            if (countItem.length > 0 && (json.migrating.trim() === 'images' || json.migrating.trim() === 'files') && parseInt(json.nb_group_table) > 0) {
                countItem.text(json.nb_group_table);
            }
        }
    },
    pauseTask: function () {
        var current_task = $('.ets_em_process_item_icon span.dot-flashing');
        if (current_task.length > 0) {
            current_task.removeClass('dot-flashing');
            ETS_EM_CURRENT_TASK = current_task.parents('.ets_em_process_item').data('task');
        }
        $('.action_start').removeClass('disabled');
        $('.action_pause').addClass('disabled');
        etsMigrateAdmin.updateMigrationBeforeUnload();
    },
    playTask: function () {
        if (ETS_EM_CURRENT_TASK && etsMigrateAdmin.isSafeMigratingName(ETS_EM_CURRENT_TASK)) {
            var current_task = $('.ets_em_process_item.' + ETS_EM_CURRENT_TASK + ' .ets_em_process_item_icon span');
            if (!current_task.hasClass('dot-flashing')) {
                current_task.addClass('dot-flashing');
            }
        }
        $('.action_start').addClass('disabled');
        $('.action_pause').removeClass('disabled');
        etsMigrateAdmin.updateMigrationBeforeUnload();
    },
    updateTaskProgress: function (json) {
        if (!etsMigrateAdmin.isSafeMigratingName(json.migrating)) {
            return;
        }
        if (json.migrating === 'finished') {
            $('.ets_em_action .action_setting').addClass('disabled');
        }
        $('.ets_em_process_item_content.' + json.migrating).removeClass('item_waiting').addClass('item_processing');
        $('.ets_em_process_item_icon.' + json.migrating + ' span').addClass('dot-flashing');
        $('.ets_em_process_item_percent.' + json.migrating).text(json.percent.toFixed(2) + '%');
        $('.ets_em_process_item_running.' + json.migrating).css('width', json.percent.toFixed(2) + '%');
    },
    copyToClipboard: function (el) {
        el.select();
        document.execCommand('copy');
        showSuccessMessage(ets_em_copied_translate);
    },
    postMigrationRequest: function (data) {
        etsMigrateAdmin.cleanError();
        etsMigrateAdmin.clearFieldErrors();
        etsMigrateAdmin.startMigrationTimer();
        if (ETS_EM_REQUEST_URL) {
            // Cancel all request.
            if (ETS_EM_PROCESS_STOP) {
                if (ETS_EM_TIMER)
                    ETS_EM_TIMER.pause();
                etsMigrateAdmin.pauseTask();
                if (ETS_EM_XHR_POOL)
                    etsMigrateAdmin.abortAll(ETS_EM_XHR_POOL);

                return false;
            }
            data['ajax'] = 1;
            $.ajax({
                type: 'POST',
                data: data,
                url: ETS_EM_REQUEST_URL,
                dataType: 'json',
                beforeSend: function (jqXHR) {
                    ETS_EM_XHR_POOL.push(jqXHR);
                },
                complete: function (jqXHR) {
                    ETS_EM_XHR_POOL = ETS_EM_XHR_POOL.filter(function (xhr) {
                        return xhr !== jqXHR;
                    });
                },
                success: function (json) {
                    ETS_EM_RETRY = 0;
                    if (json) {
                        if (json.warning) {
                            etsMigrateAdmin.showWarningMessage(json.warning);
                        }
                        if (json.adaptive_retry) {
                            etsMigrateAdmin.displayTask(json);
                            etsMigrateAdmin.postMigrationRequest(data);
                            return;
                        }
                        if (json.error || json.errors) {
                            // Pause:
                            ETS_EM_PROCESS_STOP = true;
                            if (ETS_EM_TIMER)
                                ETS_EM_TIMER.pause();
                            etsMigrateAdmin.pauseTask();
                            if (ETS_EM_XHR_POOL)
                                etsMigrateAdmin.abortAll(ETS_EM_XHR_POOL);
                            // End pause:
                            if (json.field_errors) {
                                etsMigrateAdmin.applyFieldErrors(json.field_errors);
                            }
                            if (json.error) {
                                etsMigrateAdmin.showErrorMessage(json.error);
                            }
                            if (json.errors) {
                                etsMigrateAdmin.showErrorMessage(json.errors);
                            }
                        } else {
                            if (json.migrating !== ETS_EM_MIGRATE_RESOURCE) {
                                ETS_EM_MIGRATE_RESOURCE = json.migrating
                            }
                            if (json.idle && !json.end) {
                                ETS_EM_PROCESS_STOP = true;
                                if (ETS_EM_TIMER) {
                                    ETS_EM_TIMER.pause();
                                }
                                etsMigrateAdmin.pauseTask();
                                etsMigrateAdmin.showErrorMessage(ets_em_migration_queue_empty);
                                etsMigrateAdmin.updateMigrationBeforeUnload();
                                return;
                            }
                            if (json.ok && !json.end) {
                                etsMigrateAdmin.displayTask(json);
                                etsMigrateAdmin.postMigrationRequest(data);
                            } else if (json.end) {
                                etsMigrateAdmin.completeMigration(json);
                            }
                        }
                    }
                    etsMigrateAdmin.updateMigrationBeforeUnload();
                    if ($('li.ets-em-nav-step.step3.active').length <= 0) {
                        $('body').removeClass('ets-em-step3-active');
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    if (ETS_EM_PROCESS_STOP) {
                        return;
                    }
                    if (ETS_EM_RETRY < ETS_EM_MAX_RETRY) {
                        ETS_EM_RETRY++;
                        setTimeout(function () {
                            etsMigrateAdmin.postMigrationRequest(data);
                        }, ETS_EM_RETRY_DELAY);
                        return;
                    }
                    etsMigrateAdmin.showErrorMessage(ets_em_ajax_error);
                    ETS_EM_PROCESS_STOP = true;
                    if (ETS_EM_TIMER)
                        ETS_EM_TIMER.pause();
                    etsMigrateAdmin.pauseTask();
                    if (ETS_EM_XHR_POOL)
                        etsMigrateAdmin.abortAll(ETS_EM_XHR_POOL);
                }
            });
        }
    },
    completeMigration: function (json) {
        $('.ets_em_overlay.active').removeClass('active');
        etsMigrateAdmin.displayTask(json);
        if (ETS_EM_TIMER) {
            ETS_EM_TIMER.stop();
        }
        var cookieKey = '';
        if (json.cookie_key) {
            cookieKey = json.cookie_key;
        } else if (json.new_cookie_key) {
            cookieKey = json.new_cookie_key;
        } else {
            var cookieInput = $('#ETS_EM_SOURCE_COOKIE_KEY');
            if (cookieInput.length) {
                var cookieVal = cookieInput.val();
                if (cookieVal) {
                    cookieKey = cookieVal;
                }
            }
        }
        if (cookieKey && String(cookieKey).trim() !== '') {
            $('.ets_em_keycode').val(String(cookieKey).trim());
        }
        if (json.images) {
            $('.ets_em_todo_list.images').show();
            $('.ets_em_todo_list.images .ets_em_todo_list_item').hide();
            $.each(json.images, function (i, item) {
                $('.ets_em_todo_list.images .ets_em_todo_list_item.' + i).show();
                if (json.ps_root_dir) {
                    $('.ets_em_todo_list.images .ets_em_todo_list_item.' + i + ' .ets_em_todo_url .path').text(json.ps_root_dir);
                }
            });
        } else {
            $('.ets_em_todo_list.images').hide();
        }
        if (json.products_thumb) {
            $('.ets_em_todo_list.products').show();
        } else {
            $('.ets_em_todo_list.products').hide();
        }
        if (json.files) {
            $('.ets_em_todo_list.files').show();
            $('.ets_em_todo_list.files .ets_em_todo_list_item').hide();
            $.each(json.files, function (i, item) {
                $('.ets_em_todo_list.files .ets_em_todo_list_item.' + i).show();
                if (json.ps_root_dir) {
                    $('.ets_em_todo_list.files .ets_em_todo_list_item.' + i + ' .ets_em_todo_url .path').text(json.ps_root_dir);
                }
            });
        } else {
            $('.ets_em_todo_list.files').hide();
        }
        if (json.keep_pwd || json.images || json.files || json.products_thumb || json.show_employee_password_reset_notice || json.followup_csblog) {
            $('.ets_em_title_sub').show();
        } else {
            $('.ets_em_title_sub').hide();
        }
        if (json.keep_pwd) {
            $('.ets_em_keep_password.ets_em_thankyou').show();
        } else {
            $('.ets_em_keep_password.ets_em_thankyou').hide();
        }
        if (json.show_employee_password_reset_notice) {
            $('#ets_em_employee_password_reset_notice').show();
            var resetList = $('#ets_em_employee_password_reset_notice .ets_em_employee_password_reset_list').empty();
            var resetEmails = $.isArray(json.employee_password_reset_emails) ? json.employee_password_reset_emails : [];
            $.each(resetEmails, function (index, email) {
                resetList.append($('<li>').text(email));
            });
            $('#ets_em_employee_password_reset_notice .ets_em_employee_password_reset_list_title').toggle(resetEmails.length > 0);
        } else {
            $('#ets_em_employee_password_reset_notice').hide();
        }
        if (json.followup_csblog) {
            $('#ets_em_csblog_followup').show();
        } else {
            $('#ets_em_csblog_followup').hide();
        }
        if (json.keep_pwd_error) {
            etsMigrateAdmin.showWarningMessage(json.keep_pwd_error);
        }
        etsMigrateAdmin.finalizeMigration();
        etsMigrateAdmin.unbindMigrationBeforeUnload();
        etsMigrateAdmin.navigateWizardSteps($('.ets-em-nav-step[data-step=4]'));
    },
    finalizeMigration: function () {
        if (!ETS_EM_REQUEST_URL) {
            return;
        }
        $.ajax({
            type: 'POST',
            url: ETS_EM_REQUEST_URL,
            data: {
                ajax: 1,
                action: 'finalizeMigration'
            },
            dataType: 'json',
            success: function (json) {
                if (!json) {
                    return;
                }
                if (json.warning) {
                    etsMigrateAdmin.showWarningMessage(json.warning);
                }
                if (json.error) {
                    etsMigrateAdmin.showErrorMessage(json.error);
                    return;
                }
                if (json.errors) {
                    etsMigrateAdmin.showErrorMessage(json.errors);
                }
            },
            error: function () {
                etsMigrateAdmin.showErrorMessage(ets_em_ajax_error);
            }
        });
    },
    updateMigrateSpeedSlider: function (form) {
        if (form.length > 0) {
            var speed = form.find('#ETS_EM_MIGRATE_SPEED').eq(0),
                speed_val = form.find('#ETS_EM_MIGRATE_SPEED_value').eq(0)
            ;
            if (speed.length > 0) {
                speed_val.html(speed.val());
                var max = speed.attr('max'),
                    min = speed.attr('min'),
                    step = speed.attr('step'),
                    total_range = step > 0 ? (max - min) / step : max - min,
                    current_range = (speed.val() - min) / step, percent = (current_range / total_range) * 100
                ;
                form.find('span.ets_range_input_val').css('width', percent + '%');
                speed_val.css('left', percent + '%');
            }
        }
    },
    toggleMigrationOptions: function () {
        var choice = parseInt($('input[name=ETS_EM_MIGRATION_METHOD]:checked').val());
        var keepOldId = ETS_EM_KEEP_OLD_ID_MODES.indexOf(choice) !== -1;
        if (keepOldId) {
            $('.form-group.ets_em_migrate_images:not(.disabled), .form-group.ets_em_generate_product_thumbnail:not(.disabled), .form-group.ets_em_migrate_image_speed:not(.disabled)').show();
            $('.form-group.ets_em_attachments_files:not(.disabled), .form-group.ets_em_attachments_files_speed:not(.disabled)').show();
        } else {
            $('.form-group.ets_em_migrate_images:not(.disabled), .form-group.ets_em_generate_product_thumbnail:not(.disabled), .form-group.ets_em_migrate_image_speed:not(.disabled)').hide();
            $('.form-group.ets_em_attachments_files:not(.disabled), .form-group.ets_em_attachments_files_speed:not(.disabled)').hide();
        }
        etsMigrateAdmin.migrateImage();
        etsMigrateAdmin.migrateFiles();
        etsMigrateAdmin.migrateProductThumbnail();
    },
    initMigrateAdmin: function () {
        etsMigrateAdmin.navigateWizardSteps($('.ets-em-nav-step.active').length > 0 ? $('.ets-em-nav-step.active') : $('.ets-em-nav-step:first'));
        var speedForms = $('form');
        if (speedForms.length > 0) {
            speedForms.each(function () {
                etsMigrateAdmin.updateMigrateSpeedSlider($(this));
            });
        }
        etsMigrateAdmin.migrateImage();
        etsMigrateAdmin.migrateFiles();
        etsMigrateAdmin.toggleMigrationOptions();
        if (ETS_EM_DATA_INFO_SOURCE) {
            etsMigrateAdmin.prefillFormFromServer(ETS_EM_DATA_INFO_SOURCE);
        }
    },
    abortAll: function (xhrPool) {
        if (xhrPool) {
            $.each(xhrPool, function (index, xhr) {
                if (xhr && xhr.readyState !== 4) {
                    xhr.abort();
                }
            });
        }
    },
    migrateImage: function (option) {
        var choice = option || $('input[name=ETS_EM_MIGRATE_IMAGES]:checked').val();
        var method = parseInt($('input[name=ETS_EM_MIGRATION_METHOD]:checked').val());
        if (ETS_EM_KEEP_OLD_ID_MODES.indexOf(method) === -1 || choice.trim() === 'manual') {
            $('.form-group.ets_em_generate_product_thumbnail:not(.disabled), .form-group.ets_em_migrate_image_speed:not(.disabled)').hide();
        } else {
            $('.form-group.ets_em_generate_product_thumbnail:not(.disabled), .form-group.ets_em_migrate_image_speed:not(.disabled)').show();
        }
    },
    startMigrationTimer: function () {
        var clock = $('.ets_em_timerun_clock');
        if (!ETS_EM_TIMER) {
            ETS_EM_TIMER = {
                hours: 0,
                minutes: 0,
                seconds: 0,
                intervalId: null,
                running: false,
                updateClock: function () {
                    clock.find('.hours > .number').html(this.hours);
                    clock.find('.minutes > .number').html(this.minutes);
                    clock.find('.second > .number').html(this.seconds);
                },
                start: function () {
                    var timer = this;
                    if (timer.running) {
                        return;
                    }
                    timer.running = true;
                    timer.intervalId = setInterval(function () {
                        timer.seconds += 1;
                        if (timer.seconds >= 60) {
                            timer.seconds = 0;
                            timer.minutes += 1;
                        }
                        if (timer.minutes >= 60) {
                            timer.minutes = 0;
                            timer.hours += 1;
                        }
                        timer.updateClock();
                    }, 1000);
                },
                pause: function () {
                    if (this.intervalId) {
                        clearInterval(this.intervalId);
                        this.intervalId = null;
                    }
                    this.running = false;
                },
                stop: function () {
                    this.pause();
                    this.hours = 0;
                    this.minutes = 0;
                    this.seconds = 0;
                    this.updateClock();
                }
            };
            ETS_EM_TIMER.start();
        } else {
            ETS_EM_TIMER.start();
        }
        clock.show();
    },
    newMigrate: function (btn) {
        if (!btn.hasClass('active') && ETS_EM_REQUEST_URL !== '') {
            etsMigrateAdmin.cleanError();
            btn.addClass('active');
            $.ajax({
                type: 'POST',
                url: ETS_EM_REQUEST_URL,
                data: {
                    action: 'new_migrate',
                    ajax: 1,
                },
                dataType: 'json',
                success: function (json) {
                    btn.removeClass('active');
                    if (json) {
                        if (json.warning) {
                            etsMigrateAdmin.showWarningMessage(json.warning);
                        }
                        if (json.errors) {
                            etsMigrateAdmin.showErrorMessage(json.errors);
                        } else if (json.new_migrate) {
                            location.reload();
                        }
                    }
                },
                error: function () {
                    btn.removeClass('active');
                    etsMigrateAdmin.showErrorMessage(ets_em_ajax_error);
                }
            });
        }
    },
    prefillFormFromServer: function (json) {
        if (json) {
            if (json.ps_version) {
                $('#ETS_EM_MIGRATE_VERSION').val(json.ps_version);
            }
            if (json.new_cookie_key) {
                $('#ETS_EM_SOURCE_COOKIE_KEY').val(json.new_cookie_key);
            } else if (json.cookie_key) {
                $('#ETS_EM_SOURCE_COOKIE_KEY').val(json.cookie_key);
            }
            if (json.nb) {
                Object.keys(json.nb).forEach(function (prop) {
                    if (!etsMigrateAdmin.isSafeMigratingName(prop) || !json.nb[prop]) {
                        return;
                    }
                    var count = parseInt(json.nb[prop]['nb'], 10);
                    if (count > 0) {
                        $('#ETS_EM_DATA_TO_MIGRATE_' + prop).prop('checked', true);
                        $('.nb_' + prop).text(String(count));
                    } else {
                        $('#ETS_EM_DATA_TO_MIGRATE_' + prop).prop('checked', false);
                        $('.data_to_migrate.' + prop).remove();
                    }
                });
            }
            if (json.source_shops && json.target_shops) {
                var count_source_shop = Object.keys(json.source_shops).length,
                    count_target_shop = Object.keys(json.target_shops).length
                ;
                if (count_source_shop > 1 || count_target_shop > 1) {
                    var mappingList = $('.ets-em-shop-mapping-list'),
                        shopList = [],
                        targetShopOptions = []
                    ;
                    $('.form-group.ets-em-shop-mapping').show();
                    if (count_source_shop > 1) {
                        targetShopOptions.push($('<option>').val('-1').text(ets_em_do_not_import));
                    }
                    Object.keys(json.target_shops).forEach(function (targetKey) {
                        var targetShop = json.target_shops[targetKey];
                        targetShopOptions.push($('<option>').val(targetShop.id_shop).text(targetShop.name));
                    });
                    targetShopOptions.push($('<option>').val('0').text(ets_em_create_shop));
                    mappingList.empty();
                    Object.keys(json.source_shops).forEach(function (sourceKey) {
                        var sourceShop = json.source_shops[sourceKey];
                        var sourceShopId = sourceShop.id_shop;
                        var sourceSelect = $('<select class="col-lg-4"></select>')
                            .attr('id', 'id_shop_source_' + sourceShopId)
                            .attr('name', 'id_shop_source_' + sourceShopId);
                        sourceSelect.append($('<option>').val(sourceShopId).text(sourceShop.name));
                        var targetSelect = $('<select class="col-lg-4"></select>')
                            .attr('id', 'id_shop_target_' + sourceShopId)
                            .attr('name', 'id_shop_target_' + sourceShopId);
                        $.each(targetShopOptions, function (optionIndex, optionElement) {
                            targetSelect.append(optionElement.clone());
                        });
                        var sourceShopIdString = String(sourceShopId);
                        if (targetSelect.find('option[value="' + sourceShopIdString + '"]').length) {
                            targetSelect.val(sourceShopIdString);
                        } else {
                            var firstTargetShopId = '0';
                            targetSelect.find('option').each(function () {
                                var optionValue = parseInt($(this).val(), 10);
                                if (optionValue > 0) {
                                    firstTargetShopId = String(optionValue);
                                    return false;
                                }
                            });
                            targetSelect.val(firstTargetShopId);
                        }
                        var mappingItem = $('<div class="shop-mapping-item form-group"></div>');
                        mappingItem.append(sourceSelect);
                        mappingItem.append($('<span class="ets_svg_icon"><svg class="w_14 h_14" width="14" height="14" viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg"><path d="M1600 960q0 54-37 91l-651 651q-39 37-91 37-51 0-90-37l-75-75q-38-38-38-91t38-91l293-293h-704q-52 0-84.5-37.5t-32.5-90.5v-128q0-53 32.5-90.5t84.5-37.5h704l-293-294q-38-36-38-90t38-90l75-75q-38-38 90-38 53 0 91 38l651 651q37 35 37 90z"/></svg></span>'));
                        mappingItem.append(targetSelect);
                        mappingList.append(mappingItem);
                        shopList.push(sourceShopId);
                    });
                    $('#ETS_EM_SHOPS_MAPPING').val(shopList.join(','));
                } else if ($('#current_step').val().trim() === '1') {
                    $('.form-group.ets-em-shop-mapping').hide();
                }
            }
            if (json.migrate) {
                $('.ets_em_form_group.active').removeClass('active');
                $('.ets_em_migrate_popup').html(json.migrate);
                $('.ets_em_form_group.migrate_review,.form-group.wrap-migrate').addClass('active');
                $('input[type=hidden][name=migrate_option]').val(1);
                $('.form-group.footer-step2.migrate, button.ets_em_advanced_settings').show();
                $('.form-group.footer-step2 .ets_em_form_submit_btn').text(ets_em_migrate_now);
                $('.form-group.footer-step2.migrate-resume, button.ets_em_popup_cancel').hide();
                $('.ets_em_migrate_option p').each(function () {
                    var option = $('.form-group.ets_migrate_option.' + $(this).data('id').toLowerCase());
                    if (option.css('display') === 'none' || option.hasClass('disabled')) {
                        $(this).hide();
                    } else {
                        $(this).show();
                    }
                });
            }
            if (json.confirm) {
                $('.ets_em_form_group.active').removeClass('active');
                $('.form-group.wrap-migrate, .ets_em_form_group.migrate_resume').addClass('active');
                $('.form-group.footer-step2.migrate').hide();
                $('.form-group.footer-step2.migrate-resume').show();
            }
            if (parseInt(json.images) <= 0) {
                $('.form-group.group-images').addClass('disabled');
            }
            if (parseInt(json.files) <= 0) {
                $('.form-group.group-files').addClass('disabled');
            }
            etsMigrateAdmin.toggleEmptyCartOption();
            etsMigrateAdmin.toggleMediaGroupVisibility();
            etsMigrateAdmin.migrateSupplierAndManufacturer();
            etsMigrateAdmin.migrateProductThumbnail();
        }
    },
    migrateFiles: function (option) {
        var choice = option || $('input[name=ETS_EM_ATTACHMENTS_FILES]:checked').val();
        if (choice.trim() === 'manual') {
            $('.form-group.ets_em_attachments_files_speed:not(.disabled)').hide();
        } else {
            $('.form-group.ets_em_attachments_files_speed:not(.disabled)').show();
        }
    },
    toggleEmptyCartOption: function () {
        if ($('input[id^=ETS_EM_DATA_TO_MIGRATE_orders]:checked').length > 0) {
            $('.form-group.ets_em_migrate_empty_cart').show();
        } else {
            $('.form-group.ets_em_migrate_empty_cart').hide();
        }
    },
    toggleMediaGroupVisibility: function () {
        if ($('.data_to_migrate.group-images input[id^=ETS_EM_DATA_TO_MIGRATE]:not([id$=ALL]):not([id$=minor_data]):checked').length > 0) {
            $('.form-group.group-images').removeClass('hide');
        } else {
            $('.form-group.group-images').addClass('hide');
        }
        if ($('.data_to_migrate.group-files input[id^=ETS_EM_DATA_TO_MIGRATE]:not([id$=ALL]):not([id$=minor_data]):checked').length > 0) {
            $('.form-group.group-files').removeClass('hide');
        } else {
            $('.form-group.group-files').addClass('hide');
        }
        etsMigrateAdmin.toggleOptionGroupTitles();
    },
    migrateSupplierAndManufacturer: function () {
        if ($('#ETS_EM_DATA_TO_MIGRATE_supplier').is(':checked')) {
            $('.ets_migrate_option.ets_em_supplier_default').hide();
        } else {
            $('.ets_migrate_option.ets_em_supplier_default').show();
        }

        if ($('#ETS_EM_DATA_TO_MIGRATE_manufacturer').is(':checked')) {
            $('.ets_migrate_option.ets_em_manufacturer_default').hide();
        } else {
            $('.ets_migrate_option.ets_em_manufacturer_default').show();
        }
        etsMigrateAdmin.toggleOptionGroupTitles();
    },
    toggleOptionGroupTitles: function () {
        $('.ets_em_form_group.advanced_settings h4.ets_em_option_group_title').each(function () {
            var title = $(this),
                hasVisibleOption = title.nextUntil('h4.ets_em_option_group_title').filter('.form-group').filter(function () {
                    return $(this).css('display') !== 'none' && !$(this).hasClass('hide') && !$(this).hasClass('disabled');
                }).length > 0
            ;
            title.toggle(hasVisibleOption);
        });
    },
    migrateProductThumbnail: function () {
        var method = parseInt($('input[name=ETS_EM_MIGRATION_METHOD]:checked').val());
        if ($('#ETS_EM_DATA_TO_MIGRATE_product').is(':checked') && ETS_EM_KEEP_OLD_ID_MODES.indexOf(method) !== -1 && $('input[name=ETS_EM_MIGRATE_IMAGES]:checked').val() !== 'manual') {
            $('.ets_migrate_option.ets_em_generate_product_thumbnail').show();
        } else {
            $('.ets_migrate_option.ets_em_generate_product_thumbnail').hide();
        }
    },
    cleanError: function () {
        $('#growls .growl-error').remove();
    },
    clearFieldErrors: function () {
        $('.ets_em_form .form-group').removeClass('has-error');
        $('.ets_em_form .ets_em_field_error').remove();
    },
    applyFieldErrors: function (fieldErrors) {
        etsMigrateAdmin.clearFieldErrors();
        if (!fieldErrors || typeof fieldErrors !== 'object') {
            return;
        }
        $.each(fieldErrors, function (field, message) {
            if (!message) {
                return;
            }
            var $input = $('.ets_em_form [name="' + field + '"], .ets_em_form #' + field).first();
            if (!$input.length) {
                $input = $('.ets_em_form [name^="' + field + '"]').first();
            }
            if (!$input.length && field === 'ETS_EM_SHOPS_MAPPING') {
                $input = $('.ets_em_form select[id^=id_shop_target]').first();
            }
            var $group = $input.closest('.form-group');
            if (!$group.length) {
                return;
            }
            $group.addClass('has-error');
            $group.append($('<div class="ets_em_field_error help-block"></div>').text($('<div>').html(String(message)).text()));
        });
    },
    showErrorMessage: function (msg) {
        etsMigrateAdmin.cleanError();
        var message = msg;
        if (typeof message !== 'string') {
            message = String(message);
        }
        var trimmedMessage = message.replace(/^\s+/, '');
        if (/^<!DOCTYPE/i.test(trimmedMessage) || /^<html[\s>]/i.test(trimmedMessage) || /<\s*html[\s>]/i.test(trimmedMessage)) {
            message = ets_em_ajax_error;
        }
        var plainMessage = $('<div>').html(message).text();
        var escapedMessage = $('<div>').text(plainMessage).html();
        $.growl.error({title: "", message: escapedMessage, duration: 10000});
    },
    showWarningMessage: function (msg) {
        if (!msg) {
            return;
        }
        var message = typeof msg === 'string' ? msg : String(msg);
        var plainMessage = $('<div>').html(message).text();
        var escapedMessage = $('<div>').text(plainMessage).html();
        if ($.growl && typeof $.growl.warning === 'function') {
            $.growl.warning({title: "", message: escapedMessage, duration: 10000});
            return;
        }
        $.growl.notice({title: "", message: escapedMessage, duration: 10000});
    },
    showConfirmModal: function (options) {
        var opts = options || {};
        var $modal = $('#ets_em_confirm_modal');
        if (!$modal.length) {
            if (typeof opts.onConfirm === 'function') {
                opts.onConfirm();
            }
            return;
        }
        $('#ets_em_confirm_modal_title').text(opts.title || '');
        $('#ets_em_confirm_modal_message').html(opts.message || '');
        var $btnConfirm = $('#ets_em_confirm_modal_btn_confirm');
        var $btnCancel = $('#ets_em_confirm_modal_btn_cancel');

        $btnConfirm.text(opts.confirmButtonText || (typeof ets_em_migrate !== 'undefined' ? ets_em_migrate : 'Continue'));
        $btnCancel.text(opts.cancelButtonText || 'Cancel');

        if (opts.hideCancel) {
            $btnCancel.hide();
        } else {
            $btnCancel.show();
        }

        $btnConfirm.off('click').on('click', function () {
            etsMigrateAdmin.hideConfirmModal();
            if (typeof opts.onConfirm === 'function') {
                opts.onConfirm();
            }
        });

        $btnCancel.off('click').on('click', function () {
            etsMigrateAdmin.hideConfirmModal();
            if (typeof opts.onCancel === 'function') {
                opts.onCancel();
            }
        });

        $modal.find('.close').off('click').on('click', function () {
            etsMigrateAdmin.hideConfirmModal();
            if (typeof opts.onCancel === 'function') {
                opts.onCancel();
            }
        });

        if (typeof $.fn.modal === 'function') {
            $modal.modal({backdrop: 'static', keyboard: true, show: true});
        } else {
            $modal.addClass('in show').show();
        }
    },
    hideConfirmModal: function () {
        var $modal = $('#ets_em_confirm_modal');
        if (typeof $.fn.modal === 'function') {
            $modal.modal('hide');
        } else {
            $modal.removeClass('in show').hide();
        }
        $('.modal-backdrop').remove();
    },
};

$(document).ready(function () {
    etsMigrateAdmin.initMigrateAdmin();
    $(document).on('input change', '#ETS_EM_MIGRATE_SPEED', function () {
        etsMigrateAdmin.updateMigrateSpeedSlider($(this).parents('form').eq(0));
    });
    $('.ets_em_keycode').click(function (e) {
        e.preventDefault();
        etsMigrateAdmin.copyToClipboard($(this));
    });
    $(document).on('click', '.viewmore_button.more', function (e) {
        $(this).removeClass('active');
        $('.viewmore_button.less').addClass('active');
        $(this).parent().prev('.ets_em_wrap').addClass('more').removeClass('less');

    });
    $(document).on('click', '.viewmore_button.less', function (e) {
        $(this).removeClass('active');
        $('.viewmore_button.more').addClass('active');
        $(this).parent().prev('.ets_em_wrap').addClass('less').removeClass('more');
    });
    $(document).on('click', '.back_step1', function (e) {
        e.preventDefault();
        etsMigrateAdmin.navigateWizardSteps($('.ets-em-nav-step.active:not(:first-child)').prev());
    });

    $(document).on('click', '#module_form_submit_btn, .ets_em_form_submit_btn, .ets_em_migrate_resume', function (ev) {
        ev.preventDefault();
        var next = $(this),
            newSetting = next.hasClass('ets_em_setting') ? 1 : 0,
            currentStep = $('.ets-em-nav-step.active')
        ;
        etsMigrateAdmin.cleanError();
        etsMigrateAdmin.clearFieldErrors();
        if (!newSetting && parseInt(currentStep.data('step')) > 1 && $('.ets_em_form_group.advanced_settings.active').length > 0 && $('input[id^=ETS_EM_DATA_TO_MIGRATE]:not([id$=ALL]):not([id$=minor_data]):checked').length <= 0) {
            etsMigrateAdmin.showConfirmModal({
                title: typeof ets_em_empty_data_title !== 'undefined' ? ets_em_empty_data_title : 'Select data to migrate',
                message: typeof ets_em_empty_data_message !== 'undefined' ? ets_em_empty_data_message : (typeof ets_em_migrate_data_empty !== 'undefined' ? ets_em_migrate_data_empty : 'Select at least one data type to migrate.'),
                confirmButtonText: typeof ets_em_modal_btn_understood !== 'undefined' ? ets_em_modal_btn_understood : 'Understood',
                hideCancel: true
            });
            return;
        }
        var runSubmitAjax = function () {
            if (ETS_EM_REQUEST_URL && !next.hasClass('active') && (newSetting || parseInt(currentStep.data('step')) < 3)) {
                next.addClass('active');
                var formData = new FormData();
                if (next.hasClass('ets_em_migrate_resume')) {
                    formData.append('current_step', currentStep.data('step'));
                    formData.append('migrate_resume', 1);
                    formData.append('ETS_EM_MIGRATE_SPEED', $('#ETS_EM_MIGRATE_SPEED').val());
                } else {
                    formData = new FormData(next.parents('form').get(0));
                    if (newSetting) {
                        formData.append('new_setting', 1);
                    }
                }
                $.ajax({
                    type: 'POST',
                    url: ETS_EM_REQUEST_URL + '&ajax=1&action=migrate',
                    dataType: 'json',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (json) {
                        next.removeClass('active');
                        if (json) {
                            if (json.warning) {
                                etsMigrateAdmin.showWarningMessage(json.warning);
                            }
                            if (json.adaptive_retry) {
                                ETS_EM_PROCESS_STOP = false;
                                etsMigrateAdmin.playTask();
                                etsMigrateAdmin.displayTask(json);
                                etsMigrateAdmin.postMigrationRequest({current_step: 3, action: 'migrate'});
                                return;
                            }
                            if (json.errors || json.error) {
                                // Pause:
                                ETS_EM_PROCESS_STOP = true;
                                if (ETS_EM_TIMER)
                                    ETS_EM_TIMER.pause();
                                etsMigrateAdmin.pauseTask();
                                if (ETS_EM_XHR_POOL)
                                    etsMigrateAdmin.abortAll(ETS_EM_XHR_POOL);
                                // End pause:
                                if (json.field_errors) {
                                    etsMigrateAdmin.applyFieldErrors(json.field_errors);
                                }
                                if (json.errors)
                                    etsMigrateAdmin.showErrorMessage(json.errors);
                                if (json.error)
                                    etsMigrateAdmin.showErrorMessage(json.error);
                            } else if (json.end) {
                                etsMigrateAdmin.completeMigration(json);
                            } else {
                                ETS_EM_PROCESS_STOP = false;
                                etsMigrateAdmin.playTask();
                                if (newSetting) {
                                    $('.ets_em_overlay.active').removeClass('active');
                                    if (json.ok) {
                                        etsMigrateAdmin.displayTask(json);
                                        etsMigrateAdmin.postMigrationRequest({current_step: 3, action: 'migrate'});
                                    }
                                } else {
                                    if (json.process) {
                                        $('.ets_em_list_process').html(json.process);
                                    }
                                    etsMigrateAdmin.displayTask(json);
                                    if (json.continue) {
                                        var continueStep = json.step ? json.step : (parseInt($('#current_step').val(), 10) || 3);
                                        etsMigrateAdmin.navigateWizardSteps($('.ets-em-nav-step[data-step=' + continueStep + ']'));
                                        etsMigrateAdmin.postMigrationRequest({current_step: continueStep, action: 'migrate'});
                                    } else {
                                        // Msg:
                                        if (json.ok && json.msg) {
                                            showSuccessMessage(json.msg);
                                        }
                                        // Number record catalog imports:
                                        etsMigrateAdmin.prefillFormFromServer(json);
                                        if (!json.migrate) {
                                            etsMigrateAdmin.navigateWizardSteps(currentStep.not(':last-child').next());
                                            if (parseInt($('#current_step').val()) === 3) {
                                                etsMigrateAdmin.postMigrationRequest({current_step: 3, action: 'migrate'});
                                            }
                                        }
                                    }
                                }
                            }
                            if ($('li.ets-em-nav-step.step3.active').length > 0) {
                                $('body').addClass('ets-em-step3-active');
                            } else {
                                $('body').removeClass('ets-em-step3-active');
                            }
                            etsMigrateAdmin.updateMigrationBeforeUnload();
                        }
                    },
                    error: function () {
                        next.removeClass('active');
                        var ajaxErrorMessage = (typeof ets_em_ajax_error !== 'undefined' && ets_em_ajax_error)
                            ? ets_em_ajax_error
                            : 'An unexpected error occurred. Please try again.';
                        if (typeof etsMigrateAdmin.showErrorMessage === 'function') {
                            etsMigrateAdmin.showErrorMessage(ajaxErrorMessage);
                        } else if (typeof showErrorMessage === 'function') {
                            showErrorMessage(ajaxErrorMessage);
                        }
                    }
                });
            } else {
                etsMigrateAdmin.postMigrationRequest({current_step: currentStep.data('step'), action: 'migrate'});
            }
        };

        if (!newSetting && !next.hasClass('ets_em_migrate_resume') && parseInt($('input[name=migrate_option]').val(), 10) === 1) {
            var migrationMethod = parseInt($('input[name=ETS_EM_MIGRATION_METHOD]:checked').val(), 10);
            if (migrationMethod === 1 || migrationMethod === 2) {
                etsMigrateAdmin.showConfirmModal({
                    title: typeof ets_em_confirm_clean_title !== 'undefined' ? ets_em_confirm_clean_title : 'Start migration?',
                    message: typeof ets_em_confirm_clean_message !== 'undefined' ? ets_em_confirm_clean_message : (typeof ets_em_confirm_clean !== 'undefined' ? ets_em_confirm_clean : 'Selected items on this store will be refreshed to match your source store. Are you ready to begin?'),
                    confirmButtonText: typeof ets_em_confirm_clean_btn !== 'undefined' ? ets_em_confirm_clean_btn : 'Start migration',
                    cancelButtonText: typeof ets_em_confirm_clean_cancel !== 'undefined' ? ets_em_confirm_clean_cancel : 'Review settings',
                    onConfirm: function () {
                        runSubmitAjax();
                    }
                });
                return;
            }
        }
        runSubmitAjax();
    });

    $(document).on('change', 'select[id^=id_shop_target]', function () {
        var flag = false,
            btn = $('.ets_em_form_submit_btn')
        ;
        $('select[id^=id_shop_target]').each(function () {
            if ($(this).val().trim() !== '-1') {
                return (flag = true);
            }
        });
        if (!flag) {
            btn.prop('disabled', true);
        } else {
            btn.prop('disabled', false);
            etsMigrateAdmin.cleanError();
            etsMigrateAdmin.clearFieldErrors();
        }
    });
    $('input[name=ETS_EM_MIGRATE_IMAGES]').change(function () {
        etsMigrateAdmin.migrateImage($(this).val());
    });
    $('input[name=ETS_EM_ATTACHMENTS_FILES]').change(function () {
        etsMigrateAdmin.migrateFiles($(this).val());
    });
    $('input[name=ETS_EM_MIGRATION_METHOD]').change(function () {
        etsMigrateAdmin.toggleMigrationOptions();
    });
    $('.ets_em_advanced_settings').not('button').click(function (ev) {
        ev.preventDefault();
        $('.ets_em_form_group').removeClass('active');
        $('.form-group.wrap-migrate, .ets_em_form_group.advanced_settings').addClass('active');
        $('input[type=hidden][name=migrate_option]').val(0);
        $('.form-group.footer-step2.migrate, button.ets_em_popup_cancel').show();
        $('.form-group.footer-step2.migrate-resume, button.ets_em_advanced_settings').hide();
        $('.form-group.footer-step2 .ets_em_form_submit_btn').text(ets_em_migrate);
    });
    $('button.ets_em_advanced_settings').click(function (ev) {
        ev.preventDefault();
        $(this).addClass('active');
        setTimeout(function () {
            $('.ets_em_form_group').removeClass('active');
            $('.form-group.wrap-migrate, .ets_em_form_group.advanced_settings').addClass('active');
            $('input[type=hidden][name=migrate_option]').val(0);
            $('.form-group.footer-step2.migrate, button.ets_em_popup_cancel').show();
            $('.form-group.footer-step2.migrate-resume, button.ets_em_advanced_settings').hide();
            $('button.ets_em_advanced_settings').removeClass('active');
            $('.form-group.footer-step2 .ets_em_form_submit_btn').text(ets_em_migrate);
        }, 350);
    });
    $(document).on('click', '.ets_em_close_popup, .ets_em_popup_cancel', function (ev) {
        ev.preventDefault();
        $('.form-group.wrap-migrate.active').removeClass('active');
        $('input[type=hidden][name=migrate_option]').val(0);
        $('.form-group.footer-step2.migrate, button.ets_em_popup_cancel').show();
        $('.form-group.footer-step2.migrate-resume, button.ets_em_advanced_settings').hide();
        $('.form-group.footer-step2 .ets_em_form_submit_btn').text(ets_em_migrate);
        var overlay = $('.ets_em_overlay.active');
        if (overlay.length > 0) {
            ETS_EM_PROCESS_STOP = false;
            etsMigrateAdmin.playTask();
            etsMigrateAdmin.postMigrationRequest({current_step: 3, action: 'migrate'});
            overlay.removeClass('active');
        }
    });
    $('#ETS_EM_DATA_TO_MIGRATE_ALL').click(function () {
        var data_to_migrate = $('input[id^=ETS_EM_DATA_TO_MIGRATE]:not([id$=shop])');
        if ($(this).is(':checked')) {
            data_to_migrate.prop('checked', true);
        } else {
            data_to_migrate.prop('checked', false);
        }
        etsMigrateAdmin.toggleEmptyCartOption();
        etsMigrateAdmin.toggleMediaGroupVisibility();
        etsMigrateAdmin.migrateSupplierAndManufacturer();
        etsMigrateAdmin.migrateProductThumbnail();
    });
    $('input[id^=ETS_EM_DATA_TO_MIGRATE]:not([id$=ALL]):not([id$=minor_data])').click(function () {
        var migrate_all = $('#ETS_EM_DATA_TO_MIGRATE_ALL');

        if (ETS_EM_MIGRATE_GROUP_ORDERS) {
            var orders = $('input[id=ETS_EM_DATA_TO_MIGRATE_orders]');
            if ($(this).val() === orders.val() && orders.is(':checked')) {
                ETS_EM_MIGRATE_GROUP_ORDERS.forEach(function (item) {
                    $('input[id=ETS_EM_DATA_TO_MIGRATE_' + item + ']').prop('checked', true);
                });
            } else if (orders.is(':checked') && ETS_EM_MIGRATE_GROUP_ORDERS.indexOf($(this).val()) !== -1) {
                if (!$(this).is(':checked')) {
                    orders.prop('checked', false);
                }
            }
        }
        if (ETS_EM_MIGRATE_GROUP_PRODUCT) {
            var product = $('input[id=ETS_EM_DATA_TO_MIGRATE_product]');
            if ($(this).val() === product.val() && product.is(':checked')) {
                ETS_EM_MIGRATE_GROUP_PRODUCT.forEach(function (item) {
                    $('input[id=ETS_EM_DATA_TO_MIGRATE_' + item + ']').prop('checked', true);
                });
            } else if (product.is(':checked') && ETS_EM_MIGRATE_GROUP_PRODUCT.indexOf($(this).val()) !== -1) {
                if (!$(this).is(':checked')) {
                    product.prop('checked', false);
                }
            }
        }
        if (!$(this).is(':checked')) {
            migrate_all.prop('checked', false);
        } else {
            var flag = true;
            $('input[id^=ETS_EM_DATA_TO_MIGRATE]:not([id$=ALL]):not([id$=minor_data])').each(function () {
                if (!$(this).is(':checked')) {
                    flag = false;
                }
            });
            migrate_all.prop('checked', flag);
        }
        etsMigrateAdmin.toggleEmptyCartOption();
        etsMigrateAdmin.toggleMediaGroupVisibility();
        etsMigrateAdmin.migrateSupplierAndManufacturer();
        etsMigrateAdmin.migrateProductThumbnail();
    });
    etsMigrateAdmin.updateMigrationBeforeUnload();
    $(document).on('click', '.ets_em_action .action_setting', function () {
        if ($(this).hasClass('disabled')) {
            return;
        }
        if ($('.ets_em_overlay:not(.active)').length > 0) {
            etsMigrateAdmin.cleanError();
            // Pause:
            ETS_EM_PROCESS_STOP = true;
            if (ETS_EM_TIMER)
                ETS_EM_TIMER.pause();
            etsMigrateAdmin.pauseTask();
            if (ETS_EM_XHR_POOL)
                etsMigrateAdmin.abortAll(ETS_EM_XHR_POOL);
            // End pause:
            var btn = $(this);
            if (!btn.hasClass('active') && ETS_EM_REQUEST_URL !== '') {
                $.ajax({
                    type: 'POST',
                    url: ETS_EM_REQUEST_URL,
                    data: {
                        ajax: 1,
                        action: 'settingForm',
                    },
                    dataType: 'json',
                    success: function (json) {
                        btn.removeClass('active');
                        if (json) {
                            if (json.warning) {
                                etsMigrateAdmin.showWarningMessage(json.warning);
                            }
                            if (json.form && ETS_EM_MIGRATE_RESOURCE !== 'finished') {
                                var wrap = $('.ets_em_form_wrap');
                                wrap.html(json.form);
                                $('.ets_em_overlay').addClass('active');
                                etsMigrateAdmin.updateMigrateSpeedSlider(wrap.find('form').eq(0));
                                $('.ets_em_form_wrap .form-wrapper-group-step.step2:not(.active)').addClass('active');
                            }
                        }
                    },
                    error: function () {
                        btn.removeClass('active');
                        etsMigrateAdmin.showErrorMessage(ets_em_ajax_error);
                    }
                });
            }
        }
    });
    $('.ets_em_action .action_cancel').click(function () {
        var $btn = $(this);
        etsMigrateAdmin.showConfirmModal({
            title: typeof ets_em_cancel_migration_title !== 'undefined' ? ets_em_cancel_migration_title : 'Stop migration?',
            message: typeof ets_em_cancel_migration_message !== 'undefined' ? ets_em_cancel_migration_message : (typeof ets_em_cancel_migration !== 'undefined' ? ets_em_cancel_migration : 'Are you sure you want to stop? Current progress will be stopped and you can restart anytime.'),
            confirmButtonText: typeof ets_em_cancel_migration_btn !== 'undefined' ? ets_em_cancel_migration_btn : 'Stop migration',
            cancelButtonText: typeof ets_em_cancel_migration_cancel !== 'undefined' ? ets_em_cancel_migration_cancel : 'Keep running',
            onConfirm: function () {
                ETS_EM_PROCESS_STOP = true;
                if (ETS_EM_TIMER)
                    ETS_EM_TIMER.pause();
                etsMigrateAdmin.pauseTask();
                if (ETS_EM_XHR_POOL)
                    etsMigrateAdmin.abortAll(ETS_EM_XHR_POOL);
                etsMigrateAdmin.abortAll(ETS_EM_XHR_POOL);
                etsMigrateAdmin.newMigrate($btn);
            }
        });
    });
    $('.ets_em_action .action_pause').click(function (e) {
        if ($(this).hasClass('disabled')) {
            return;
        }
        if (!ETS_EM_PROCESS_STOP) {
            $(this).addClass('disabled').addClass('loading');
            $('.ets_em_action .action_start.disabled').removeClass('disabled');
            $('.ets_em_list_process').addClass('pausing');
            // Pause:
            ETS_EM_PROCESS_STOP = true;
            if (ETS_EM_TIMER)
                ETS_EM_TIMER.pause();
            etsMigrateAdmin.pauseTask();
            if (ETS_EM_XHR_POOL)
                etsMigrateAdmin.abortAll(ETS_EM_XHR_POOL);
            // End pause:
            setTimeout(function (e) {
                $('.ets_em_action .action_pause.disabled').removeClass('loading');
            }, 4000);
        }
    });
    $('.ets_em_action .action_start').click(function (e) {
        if (!$(this).hasClass('disabled')) {
            if (ETS_EM_PROCESS_STOP) {
                ETS_EM_PROCESS_STOP = false;
                ETS_EM_RETRY = 0;
                etsMigrateAdmin.playTask();
                etsMigrateAdmin.postMigrationRequest({current_step: 3, action: 'migrate'});
                $(this).addClass('disabled');
                $('.ets_em_action .action_pause.disabled').removeClass('disabled');
                $('.ets_em_list_process').removeClass('pausing');
            }
        }

    });
    $('.ets_em_new_migration').click(function (e) {
        e.preventDefault();
        var $btn = $(this);
        etsMigrateAdmin.showConfirmModal({
            title: typeof ets_em_new_migration_title !== 'undefined' ? ets_em_new_migration_title : 'Start a new migration?',
            message: typeof ets_em_new_migration_message !== 'undefined' ? ets_em_new_migration_message : (typeof ets_em_new_migration !== 'undefined' ? ets_em_new_migration : 'Start a new migration? Current progress will be cleared.'),
            confirmButtonText: typeof ets_em_new_migration_btn !== 'undefined' ? ets_em_new_migration_btn : 'Start new',
            cancelButtonText: typeof ets_em_new_migration_cancel !== 'undefined' ? ets_em_new_migration_cancel : 'Stay here',
            onConfirm: function () {
                etsMigrateAdmin.newMigrate($btn);
            }
        });
    });
});
