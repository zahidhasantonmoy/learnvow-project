{{-- Shared LearnVow styles — palette and type from the v3.21 prototype. --}}
<style>
  /* Self-hosted fonts (Hind Siliguri + Literata) — no Google Fonts dependency. */
/* bengali */
@font-face {
  font-family: 'Hind Siliguri';
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: url(/fonts/ijwTs5juQtsyLLR5jN4cxBEoTI7ax9k0.woff2) format('woff2');
  unicode-range: U+0951-0952, U+0964-0965, U+0980-09FE, U+1CD0, U+1CD2, U+1CD5-1CD6, U+1CD8, U+1CE1, U+1CEA, U+1CED, U+1CF2, U+1CF5-1CF7, U+200C-200D, U+20B9, U+25CC, U+A8F1, U+11DF0-11DF1;
}
/* latin-ext */
@font-face {
  font-family: 'Hind Siliguri';
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: url(/fonts/ijwTs5juQtsyLLR5jN4cxBEoTJLax9k0.woff2) format('woff2');
  unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C4, U+2113, U+2C60-2C7F, U+A720-A7FF;
}
/* latin */
@font-face {
  font-family: 'Hind Siliguri';
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: url(/fonts/ijwTs5juQtsyLLR5jN4cxBEoTJzaxw.woff2) format('woff2');
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
}
/* bengali */
@font-face {
  font-family: 'Hind Siliguri';
  font-style: normal;
  font-weight: 500;
  font-display: swap;
  src: url(/fonts/ijwOs5juQtsyLLR5jN4cxBEoRG_50vQVKxGv.woff2) format('woff2');
  unicode-range: U+0951-0952, U+0964-0965, U+0980-09FE, U+1CD0, U+1CD2, U+1CD5-1CD6, U+1CD8, U+1CE1, U+1CEA, U+1CED, U+1CF2, U+1CF5-1CF7, U+200C-200D, U+20B9, U+25CC, U+A8F1, U+11DF0-11DF1;
}
/* latin-ext */
@font-face {
  font-family: 'Hind Siliguri';
  font-style: normal;
  font-weight: 500;
  font-display: swap;
  src: url(/fonts/ijwOs5juQtsyLLR5jN4cxBEoRG_50ugVKxGv.woff2) format('woff2');
  unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C4, U+2113, U+2C60-2C7F, U+A720-A7FF;
}
/* latin */
@font-face {
  font-family: 'Hind Siliguri';
  font-style: normal;
  font-weight: 500;
  font-display: swap;
  src: url(/fonts/ijwOs5juQtsyLLR5jN4cxBEoRG_50uYVKw.woff2) format('woff2');
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
}
/* bengali */
@font-face {
  font-family: 'Hind Siliguri';
  font-style: normal;
  font-weight: 600;
  font-display: swap;
  src: url(/fonts/ijwOs5juQtsyLLR5jN4cxBEoREP-0vQVKxGv.woff2) format('woff2');
  unicode-range: U+0951-0952, U+0964-0965, U+0980-09FE, U+1CD0, U+1CD2, U+1CD5-1CD6, U+1CD8, U+1CE1, U+1CEA, U+1CED, U+1CF2, U+1CF5-1CF7, U+200C-200D, U+20B9, U+25CC, U+A8F1, U+11DF0-11DF1;
}
/* latin-ext */
@font-face {
  font-family: 'Hind Siliguri';
  font-style: normal;
  font-weight: 600;
  font-display: swap;
  src: url(/fonts/ijwOs5juQtsyLLR5jN4cxBEoREP-0ugVKxGv.woff2) format('woff2');
  unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C4, U+2113, U+2C60-2C7F, U+A720-A7FF;
}
/* latin */
@font-face {
  font-family: 'Hind Siliguri';
  font-style: normal;
  font-weight: 600;
  font-display: swap;
  src: url(/fonts/ijwOs5juQtsyLLR5jN4cxBEoREP-0uYVKw.woff2) format('woff2');
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
}
/* bengali */
@font-face {
  font-family: 'Hind Siliguri';
  font-style: normal;
  font-weight: 700;
  font-display: swap;
  src: url(/fonts/ijwOs5juQtsyLLR5jN4cxBEoRCf_0vQVKxGv.woff2) format('woff2');
  unicode-range: U+0951-0952, U+0964-0965, U+0980-09FE, U+1CD0, U+1CD2, U+1CD5-1CD6, U+1CD8, U+1CE1, U+1CEA, U+1CED, U+1CF2, U+1CF5-1CF7, U+200C-200D, U+20B9, U+25CC, U+A8F1, U+11DF0-11DF1;
}
/* latin-ext */
@font-face {
  font-family: 'Hind Siliguri';
  font-style: normal;
  font-weight: 700;
  font-display: swap;
  src: url(/fonts/ijwOs5juQtsyLLR5jN4cxBEoRCf_0ugVKxGv.woff2) format('woff2');
  unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C4, U+2113, U+2C60-2C7F, U+A720-A7FF;
}
/* latin */
@font-face {
  font-family: 'Hind Siliguri';
  font-style: normal;
  font-weight: 700;
  font-display: swap;
  src: url(/fonts/ijwOs5juQtsyLLR5jN4cxBEoRCf_0uYVKw.woff2) format('woff2');
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
}

  :root, [data-theme="light"] {
    --bg: #f3efe6; --paper: #faf5ea; --panel: #fffdf8; --card: #ffffff;
    --ink: #1d2420; --muted: #5c665f; --line: #ddd5c3;
    --green: #17573a; --green-deep: #0d3a26; --amber: #e9a13b; --amber-soft: #f7e3bd;
    --link: #17573a;
    /* UI-09: star colour — #e9a13b was 2.2:1 on light. #8a5a00 is 5.9:1
       on white / 5.5:1 on paper (≥4.5:1). Dark theme uses #f2b95c
       (8.7:1 on the dark card). */
    --star: #8a5a00;
  }
  [data-theme="dark"] {
    --bg: #101511; --paper: #171d18; --panel: #171d18; --card: #20261f;
    --ink: #ece7da; --muted: #a7b0a6; --line: #2c352c;
    --green: #2e7d54; --green-deep: #0d3a26; --amber: #e9a13b; --amber-soft: #f7e3bd;
    --link: #6fbf8f;
    --star: #f2b95c;
  }
  * { box-sizing: border-box; }
  body { margin: 0; background: var(--bg); color: var(--ink); font-family: "Hind Siliguri", system-ui, sans-serif; line-height: 1.6; }
  .serif { font-family: "Literata", Georgia, serif; }
  a { color: var(--link); text-decoration: none; }
  a:hover { text-decoration: underline; }
  .container { max-width: 1100px; margin: 0 auto; padding: 0 16px; }
  header.site-header { background: var(--panel); border-bottom: 1px solid var(--line); position: sticky; top: 0; z-index: 50; }
  .header-inner { display: flex; align-items: center; gap: 16px; padding: 10px 0; flex-wrap: wrap; }
  .brand { font-size: 1.5rem; font-weight: 700; color: var(--green-deep); }
  [data-theme="dark"] .brand { color: var(--amber); }
  .brand small { display: block; font-size: 0.75rem; color: var(--muted); font-weight: 400; }
  nav.main-nav { display: flex; gap: 14px; flex-wrap: wrap; }
  nav.main-nav a { color: var(--ink); font-weight: 500; }
  .header-tools { margin-left: auto; display: flex; align-items: center; gap: 12px; }
  .lang-switch a { padding: 2px 6px; border-radius: 4px; color: var(--muted); }
  .lang-switch a.active { background: var(--green); color: #fff; text-decoration: none; }
  .theme-btn { background: none; border: 1px solid var(--line); border-radius: 6px; padding: 4px 10px; cursor: pointer; color: var(--ink); background: var(--card); }
  main { padding: 24px 0 48px; }
  footer.site-footer { border-top: 1px solid var(--line); padding: 24px 0; color: var(--muted); font-size: 0.9rem; background: var(--panel); }
  .btn { display: inline-block; background: var(--green); color: #fff; border: none; border-radius: 8px; padding: 10px 18px; cursor: pointer; font-size: 1rem; }
  .btn:hover { text-decoration: none; background: var(--green-deep); }
  .btn-secondary { background: var(--card); color: var(--ink); border: 1px solid var(--line); }
  .book-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 16px; }
  .book-card { display: block; background: var(--card); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; color: var(--ink); }
  .book-card:hover { text-decoration: none; box-shadow: 0 4px 16px rgba(0,0,0,.12); }
  .book-card-body { padding: 10px 12px; }
  .book-card-body h3 { margin: 0 0 4px; font-size: 1rem; line-height: 1.35; }
  .book-author { margin: 0; color: var(--muted); font-size: 0.9rem; }
  .book-price { margin: 6px 0 0; font-weight: 700; color: var(--green-deep); }
  [data-theme="dark"] .book-price { color: var(--amber); }
  .book-cover { aspect-ratio: 2 / 3; display: flex; flex-direction: column; justify-content: flex-end; padding: 12px; color: #fff; }
  .book-cover-title { font-size: 1.05rem; line-height: 1.3; }
  .book-cover-author { font-size: 0.8rem; opacity: 0.85; }
  .rating { color: var(--star); font-weight: 600; font-size: 0.9rem; }
  .rating-count { color: var(--muted); font-weight: 400; }
  /* UI-10 (light pass): a generic card in the existing palette, so the
     secondary screens (wallet, premium, goals, badges, devices,
     collections, profile, checkout) match the storefront instead of
     looking like bare lists. No redesign — same card, border, radius. */
  .card { background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 16px; margin-bottom: 16px; }
  .card h2 { margin-top: 0; }
  /* UI-15: badges — earned vs unearned must read at a glance. */
  .badge-list { list-style: none; padding: 0; display: grid; gap: 10px; }
  .badge-item { border: 1px solid var(--line); border-radius: 10px; padding: 10px 12px; background: var(--card); }
  .badge-item-earned { border-color: var(--green); background: var(--card); box-shadow: inset 3px 0 0 var(--green); }
  .badge-item-locked { opacity: 0.65; border-style: dashed; }
  .badge-item-locked strong { color: var(--muted); }
  /* UI-06: EPUB reader containment guard. The +708px overflow at 375px
     was transient (epub.js resize race) and could not be reproduced
     reliably — this caps the viewer and its iframe to the viewport so a
     late resize can never push the page wide. Needs a real-device check. */
  #reader-viewer, .epub-container, .epub-view { max-width: 100%; overflow: hidden; }
  #reader-viewer iframe, .epub-view iframe { max-width: 100%; }
  /* UI-18: form controls outside .form-field (checkout method select,
     header language buttons) used the browser default — white in dark
     mode. Theme them like the rest. */
  select, input[type="text"], input[type="number"], input[type="email"], input[type="password"], input[type="tel"], textarea { background: var(--card); color: var(--ink); border: 1px solid var(--line); border-radius: 8px; padding: 8px 10px; font-size: 1rem; }
  .lang-switch button { background: none; border: none; padding: 2px 6px; border-radius: 4px; color: var(--muted); cursor: pointer; font-size: 1rem; font-family: inherit; }
  .lang-switch button.active { background: var(--green); color: #fff; }
  /* UI-05: the language buttons stacked vertically on mobile (the span
     wrapped), doubling the header height. Keep them on one line. */
  .lang-switch { display: inline-flex; align-items: center; white-space: nowrap; }
  /* UI-05: hamburger toggle — hidden on desktop, shown below 768px. */
  .nav-toggle { display: none; }
  .badge { display: inline-block; background: var(--amber-soft); color: #5a3a08; border-radius: 999px; padding: 2px 10px; font-size: 0.85rem; font-weight: 600; }
  .form-field { margin-bottom: 14px; }
  .form-field label { display: block; margin-bottom: 4px; font-weight: 600; }
  .form-field input, .form-field select, .form-field textarea { width: 100%; padding: 10px; border: 1px solid var(--line); border-radius: 8px; background: var(--card); color: var(--ink); font-size: 1rem; }
  .error { color: #b3261e; font-size: 0.9rem; }
  .alert { background: var(--amber-soft); border: 1px solid var(--amber); border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; }
  .skeleton { background: var(--line); border-radius: 8px; animation: pulse 1.2s ease-in-out infinite; }
  @keyframes pulse { 0%,100% { opacity: .6; } 50% { opacity: 1; } }
  /* UI-05: the mobile header was 203px / 4 rows at 375px. Below 768px
     the nav collapses behind a hamburger; the brand tagline hides and
     the tools stay inline. UI-07: nav links, theme toggle and language
     buttons are all ≥44px tall on small screens. UI-11: the detail
     cover is capped (140px wide / ~210px tall) instead of a ~360px
     empty colour block. */
  @media (max-width: 768px) {
    .header-inner { gap: 8px; padding: 6px 0; }
    /* N2 (re-QA 2026-11-24): the brand link was a 31px touch target at
       375px — every other header target is ≥44px. */
    .brand { font-size: 1.2rem; min-height: 44px; display: inline-flex; align-items: center; }
    .brand small { display: none; }
    .nav-toggle { display: inline-flex; align-items: center; justify-content: center; min-width: 44px; min-height: 44px; background: var(--card); color: var(--ink); border: 1px solid var(--line); border-radius: 6px; cursor: pointer; font-size: 1.25rem; }
    nav.main-nav { display: none; width: 100%; flex-direction: column; gap: 0; order: 3; }
    header.site-header.nav-open nav.main-nav { display: flex; }
    nav.main-nav a { min-height: 44px; display: flex; align-items: center; }
    /* N3 (re-QA 2026-11-24): .header-tools was 387px wide at 375px
       (12px horizontal overflow, logout cut off) because its row never
       wrapped. Let it wrap within the viewport; the hamburger is
       unaffected. */
    .header-tools { gap: 6px; flex-wrap: wrap; max-width: 100%; }
    .header-tools a, .header-tools button, .lang-switch button { min-height: 44px; display: inline-flex; align-items: center; }
    .book-detail-cover { flex: 0 0 140px !important; max-width: 140px !important; }
    .book-detail-cover .book-cover { max-height: 210px; }
  }
  @media (max-width: 640px) {
    .header-inner { gap: 8px; }
    .brand { font-size: 1.2rem; }
  }
</style>
