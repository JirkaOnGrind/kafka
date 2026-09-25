(function () {
  'use strict';

  const DEFAULT_THEME = 'black';
  const THEME_PARAM = 'theme';

  // Every foreground/background pair is chosen to meet WCAG AA. Border tokens
  // also keep at least a 3:1 contrast against adjacent component surfaces.
  const PALETTES = Object.freeze({
    black: Object.freeze({
      background: '#070809',
      panel: '#111214',
      raised: '#191b1e',
      icon: '#0b0c0d',
      line: '#62666e',
      text: '#f7f7f4',
      muted: '#b8bbc0',
      accent: '#ff6974',
      accentHover: '#ff8c95',
      accentInk: '#120204',
      focus: '#ffffff',
      heroEdge: '#070809',
      textureOpacity: '0.058',
      logoFilter: 'drop-shadow(0 0 0 transparent)'
    }),
    gray: Object.freeze({
      background: '#34373b',
      panel: '#202226',
      raised: '#292c31',
      icon: '#17191c',
      line: '#7d838d',
      text: '#fffdf8',
      muted: '#c8cbd0',
      accent: '#ff7480',
      accentHover: '#ff98a1',
      accentInk: '#160204',
      focus: '#ffffff',
      heroEdge: '#34373b',
      textureOpacity: '0.105',
      logoFilter: 'drop-shadow(0 2px 0 #111214) drop-shadow(0 0 5px rgba(255,255,255,.25))'
    }),
    red: Object.freeze({
      background: '#43090f',
      panel: '#28070b',
      raised: '#350a10',
      icon: '#170406',
      line: '#a05761',
      text: '#fff8f5',
      muted: '#e5c1c5',
      accent: '#ff9a92',
      accentHover: '#ffc1ba',
      accentInk: '#270306',
      focus: '#ffffff',
      heroEdge: '#43090f',
      textureOpacity: '0.09',
      logoFilter: 'drop-shadow(0 2px 0 #08090a) drop-shadow(0 0 6px rgba(255,248,245,.32))'
    })
  });

  const cssVariableNames = Object.freeze({
    background: '--color-background',
    panel: '--color-panel',
    raised: '--color-raised',
    icon: '--color-icon',
    line: '--color-line',
    text: '--color-paper',
    muted: '--color-muted',
    accent: '--color-rebel',
    accentHover: '--color-rebel-hover',
    accentInk: '--color-accent-ink',
    focus: '--color-focus',
    heroEdge: '--color-hero-edge',
    textureOpacity: '--texture-opacity',
    logoFilter: '--logo-filter'
  });

  function normalizeTheme(value) {
    return Object.prototype.hasOwnProperty.call(PALETTES, value) ? value : DEFAULT_THEME;
  }

  function themeFromUrl() {
    return normalizeTheme(new URL(window.location.href).searchParams.get(THEME_PARAM));
  }

  function applyTheme(value, source) {
    const theme = normalizeTheme(value);
    const root = document.documentElement;
    const palette = PALETTES[theme];

    root.dataset.theme = theme;
    root.style.colorScheme = 'dark';
    Object.entries(cssVariableNames).forEach(([key, variable]) => {
      root.style.setProperty(variable, palette[key]);
    });

    const themeColor = document.querySelector('meta[name="theme-color"]');
    if (themeColor) themeColor.content = palette.background;

    window.dispatchEvent(new CustomEvent('kafka:themechange', {
      detail: { theme, palette, source: source || 'api' }
    }));
    return theme;
  }

  function setTheme(value, options) {
    const theme = normalizeTheme(value);
    const url = new URL(window.location.href);
    const settings = options || {};

    if (theme === DEFAULT_THEME) url.searchParams.delete(THEME_PARAM);
    else url.searchParams.set(THEME_PARAM, theme);

    window.history[settings.replace ? 'replaceState' : 'pushState'](
      { ...(window.history.state || {}), theme },
      '',
      url
    );
    return applyTheme(theme, 'navigation');
  }

  const initialTheme = applyTheme(themeFromUrl(), 'initial');

  window.addEventListener('popstate', () => applyTheme(themeFromUrl(), 'popstate'));
  window.kafkaTheme = Object.freeze({
    palettes: PALETTES,
    defaultTheme: DEFAULT_THEME,
    getTheme: () => document.documentElement.dataset.theme || initialTheme,
    setTheme,
    syncFromUrl: () => applyTheme(themeFromUrl(), 'url-sync')
  });
})();
