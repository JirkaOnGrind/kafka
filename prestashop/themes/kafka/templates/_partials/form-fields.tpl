{extends file='parent:_partials/form-fields.tpl'}
{block name='form_field_item_other'}
  {if $field.name === 'birthday'}
    {* Native text input stays usable when JavaScript is unavailable. *}
    <input class="form-control" type="text" id="field-birthday" name="birthday"
      value="{$field.value}" autocomplete="bday"
      placeholder="{$field.availableValues.placeholder}"
      data-kafka-birthday data-date-format="{$field.availableValues.date_format|default:'Y-m-d'}"
      data-day-label="{l s='Day' d='Shop.Forms.Labels'}"
      data-month-label="{l s='Month' d='Shop.Forms.Labels'}"
      data-year-label="{l s='Year' d='Shop.Forms.Labels'}">
  {else}
    {$smarty.block.parent}
  {/if}
{/block}
