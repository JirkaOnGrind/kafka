(() => {
  'use strict';
  const enhance = () => document.querySelectorAll('input[data-kafka-birthday]').forEach(input => {
    if (input.dataset.enhanced) return;
    input.dataset.enhanced = 'true';
    const group = document.createElement('div');
    group.className = 'kafka-birthday';
    group.setAttribute('role', 'group');
    const label = document.querySelector('label[for="field-birthday"]');
    if (label) { label.id = 'birthday-label'; group.setAttribute('aria-labelledby', label.id); }
    const now = new Date();
    const parts = { day: '', month: '', year: '' };
    const value = input.value.trim();
    if (value && !/^0000/.test(value)) {
      const numbers = value.match(/\d+/g);
      const order = /^\d{4}-/.test(value) ? ['year', 'month', 'day'] :
        (input.dataset.dateFormat.match(/[dmY]/g) || ['Y', 'm', 'd']).map(k => ({d:'day',m:'month',Y:'year'})[k]);
      if (numbers?.length === 3) order.forEach((key, i) => parts[key] = String(Number(numbers[i])));
    }
    const selects = {};
    for (const [key, start, end] of [['day',1,31], ['month',1,12], ['year',now.getFullYear(),now.getFullYear()-120]]) {
      const select = document.createElement('select');
      select.className = 'form-select'; select.id = 'birthday-' + key;
      select.autocomplete = 'bday-' + key;
      const title = input.dataset[key + 'Label'];
      select.setAttribute('aria-label', title);
      select.add(new Option(title, ''));
      for (let n=start; start<=end ? n<=end : n>=end; n += start<=end ? 1 : -1) {
        const text = key === 'month' ? `${n}. ${new Intl.DateTimeFormat(document.documentElement.lang || 'cs', {month:'long'}).format(new Date(2000,n-1,1))}` : String(n);
        select.add(new Option(text, String(n)));
      }
      select.value = parts[key]; selects[key] = select; group.append(select);
    }
    const sync = () => {
      const {day,month,year} = selects;
      const any = day.value || month.value || year.value;
      Object.values(selects).forEach(s => { s.required = !!any; s.setCustomValidity(''); });
      if (day.value && month.value && year.value) {
        const date = new Date(+year.value,+month.value-1,+day.value);
        const valid = date.getFullYear() === +year.value && date.getMonth() === +month.value-1 && date.getDate() === +day.value && date <= now;
        // Native constraint validation uses the browser's localized message.
        if (!valid) { day.setCustomValidity(({cs:'Zvolte platné datum narození.',sk:'Vyberte platný dátum narodenia.',de:'Bitte wählen Sie ein gültiges Geburtsdatum.'})[document.documentElement.lang.split('-')[0]] || 'Choose a valid date of birth.'); input.value = ''; }
        else input.value = `${year.value}-${month.value.padStart(2,'0')}-${day.value.padStart(2,'0')}`;
      } else input.value = '';
      input.dispatchEvent(new Event('input', {bubbles:true}));
    };
    group.addEventListener('change', sync);
    input.type = 'hidden'; input.after(group);
    if (label) label.htmlFor = selects.day.id;
    sync();
  });
  enhance();
  new MutationObserver(enhance).observe(document.body, {childList:true, subtree:true});
})();
