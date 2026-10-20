{extends file='parent:_partials/head.tpl'}
{block name='head_icons'}
  <meta name="theme-color" content="#070809">
  <link rel="icon" href="{$urls.base_url}themes/kafka/assets/img/kafka-monster-raven.png" type="image/png">
{/block}
{block name='stylesheets' append}
  <link rel="stylesheet" href="{$urls.base_url}themes/kafka/assets/css/kafka.css?v=20261020-22-product-flow">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Black+Ops+One&amp;family=Inter:wght@400;500;600;700&amp;family=Oswald:wght@500;600;700&amp;display=swap" rel="stylesheet">
{/block}
{block name='javascript_head' append}
  <script src="{$urls.base_url}themes/kafka/assets/js/kafka-forms.js?v=20261020-1" defer></script>
  <script src="{$urls.base_url}themes/kafka/assets/js/black-theme.js"></script>
  <script src="{$urls.base_url}themes/kafka/assets/js/kafka-ui.js?v=20261020-1" defer></script>
{/block}
