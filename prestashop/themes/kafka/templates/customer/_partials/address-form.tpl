{extends file='parent:customer/_partials/address-form.tpl'}
{block name='form_field'}
  {if $field.name === 'phone' && empty($field.value) && !empty($formFields.phone_mobile.value)}
    {$field.value = $formFields.phone_mobile.value}
  {/if}
  {$smarty.block.parent}
{/block}
