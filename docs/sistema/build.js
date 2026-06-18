/** Build script for docs/sistema hub. Reads manifest.json and doc files from ../ then generates index.html. Run from repo root: node docs/sistema/build.js */
const fs = require('fs');
const path = require('path');
const SCRIPT_DIR = __dirname;
const DOCS_DIR = path.join(SCRIPT_DIR, '..');
const MANIFEST_PATH = path.join(SCRIPT_DIR, 'manifest.json');
const OUTPUT_PATH = path.join(SCRIPT_DIR, 'index.html');

function escapeForHtml(str) {
  return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function main() {
  const manifest = JSON.parse(fs.readFileSync(MANIFEST_PATH, 'utf8'));
  const docsData = {};
  for (const entry of manifest) {
    const filePath = path.join(DOCS_DIR, entry.file);
    if (!fs.existsSync(filePath)) continue;
    docsData[entry.file] = fs.readFileSync(filePath, 'utf8');
  }
  const menuItems = manifest.filter((e) => docsData[e.file]).map((e) =>
    `<li>
      <button type="button" class="doc-link" data-file="${escapeForHtml(e.file)}" title="${escapeForHtml(e.description)}">
        <span class="doc-link-title">${escapeForHtml(e.title)}</span>
        <span class="doc-desc">${escapeForHtml(e.description)}</span>
      </button>
    </li>`
  ).join('\n');
  const embeddedJson = JSON.stringify(docsData).replace(/</g, '\\u003c').replace(/>/g, '\\u003e');
  const html = `<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Documentação — Sistema</title>
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
  <style>
    :root, :root.dark { --color-background: #09090b; --color-foreground: #fafafa; --color-primary: #fafafa; --color-primary-foreground: #18181b; --color-secondary: #27272a; --color-secondary-foreground: #fafafa; --color-muted: #27272a; --color-muted-foreground: #a1a1aa; --color-accent: #27272a; --color-accent-foreground: #fafafa; --color-border: #27272a; --radius: 0.5rem; --code-bg: #18181b; --code-text: #e4e4e7; --toolbar-bg: rgba(9, 9, 11, 0.8); --table-stripe: rgba(39, 39, 42, 0.3); }
    :root.light { --color-background: #ffffff; --color-foreground: #09090b; --color-primary: #18181b; --color-primary-foreground: #fafafa; --color-secondary: #f4f4f5; --color-secondary-foreground: #18181b; --color-muted: #f4f4f5; --color-muted-foreground: #71717a; --color-accent: #f4f4f5; --color-accent-foreground: #18181b; --color-border: #e4e4e7; --code-bg: #f4f4f5; --code-text: #27272a; --toolbar-bg: rgba(255, 255, 255, 0.8); --table-stripe: rgba(244, 244, 245, 0.5); }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: var(--color-background); color: var(--color-foreground); min-height: 100vh; line-height: 1.5; -webkit-font-smoothing: antialiased; transition: background-color 0.3s, color 0.3s; }
    .layout { display: flex; height: 100vh; overflow: hidden; }
    aside { width: 320px; background: var(--color-background); border-right: 1px solid var(--color-border); display: flex; flex-direction: column; }
    .aside-header { padding: 1.5rem 1.5rem 1rem; border-bottom: 1px solid var(--color-border); display: flex; flex-direction: column; gap: 1rem; }
    .header-top { display: flex; justify-content: space-between; align-items: center; }
    .brand { display: flex; align-items: center; gap: 0.75rem; }
    .brand img { height: 28px; width: auto; }
    .aside-header h2 { font-size: 1.125rem; margin: 0; font-weight: 600; letter-spacing: -0.025em; }
    .filter-input { width: 100%; padding: 0.5rem 0.75rem; border-radius: var(--radius); border: 1px solid var(--color-border); background: var(--color-background); color: var(--color-foreground); font-size: 0.875rem; outline: none; transition: border-color 0.2s; }
    .filter-input:focus { border-color: var(--color-primary); }
    .filter-input::placeholder { color: var(--color-muted-foreground); }
    .aside-nav { padding: 1rem; overflow-y: auto; flex: 1; }
    aside ul { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.5rem; }
    .doc-link { width: 100%; text-align: left; padding: 0.75rem 1rem; background: transparent; border: 1px solid transparent; border-radius: var(--radius); color: var(--color-foreground); cursor: pointer; transition: all 0.2s; }
    .doc-link-title { font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem; display: block; }
    .doc-desc { font-size: 0.75rem; color: var(--color-muted-foreground); margin: 0; font-weight: 400; line-height: 1.4; display: block; }
    .doc-link:hover { background: var(--color-muted); }
    .doc-link.active { background: var(--color-secondary); border-color: var(--color-border); }
    .doc-link.active .doc-link-title { color: var(--color-secondary-foreground); }
    main { flex: 1; display: flex; flex-direction: column; overflow: hidden; position: relative; }
    .toolbar { padding: 1rem 2rem; border-bottom: 1px solid var(--color-border); display: flex; justify-content: flex-end; align-items: center; background: var(--toolbar-bg); backdrop-filter: blur(8px); position: sticky; top: 0; z-index: 10; height: 64px; }
    .btn { padding: 0.5rem 1rem; background: var(--color-primary); color: var(--color-primary-foreground); border: none; border-radius: var(--radius); cursor: pointer; font-size: 0.875rem; font-weight: 500; transition: opacity 0.2s; display: inline-flex; align-items: center; gap: 0.5rem; }
    .btn:hover { opacity: 0.9; }
    .btn svg { width: 16px; height: 16px; fill: currentColor; }
    .icon-btn { background: transparent; color: var(--color-foreground); border: 1px solid var(--color-border); padding: 0.5rem; border-radius: var(--radius); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: background 0.2s; }
    .icon-btn:hover { background: var(--color-muted); }
    .icon-btn svg { width: 16px; height: 16px; fill: currentColor; }
    .header-actions { display: flex; align-items: center; gap: 0.25rem; }
    .text-btn { font-family: ui-sans-serif, system-ui, sans-serif; font-weight: 700; width: 34px; height: 34px; padding: 0; }
    .text-btn.small { font-size: 0.8rem; }
    .text-btn.large { font-size: 1.1rem; }
    .content-wrapper { flex: 1; overflow-y: auto; padding: 2rem; scroll-behavior: smooth; }
    .placeholder { color: var(--color-muted-foreground); font-style: italic; text-align: center; margin-top: 20vh; font-size: 0.875rem; }
    .doc-content { display: none; max-width: 768px; margin: 0 auto; width: 100%; font-size: var(--doc-font-size, 1rem); line-height: 1.75; padding-bottom: 4rem; transition: font-size 0.2s; }
    .doc-content.active { display: block; animation: fadeIn 0.3s ease-in-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    .doc-content h1 { font-size: 2.25rem; font-weight: 800; margin-top: 0; margin-bottom: 1.5rem; letter-spacing: -0.025em; line-height: 1.1; }
    .doc-content h2 { font-size: 1.5rem; font-weight: 600; margin-top: 2em; margin-bottom: 1em; border-bottom: 1px solid var(--color-border); padding-bottom: 0.5em; }
    .doc-content h3 { font-size: 1.25rem; font-weight: 600; margin-top: 1.5em; margin-bottom: 0.5em; }
    .doc-content p { margin-top: 1.25em; margin-bottom: 1.25em; color: var(--color-foreground); opacity: 0.9; }
    .doc-content a { color: var(--color-primary); font-weight: 500; text-decoration: underline; text-underline-offset: 4px; }
    .doc-content strong { font-weight: 600; color: var(--color-foreground); }
    .doc-content ul, .doc-content ol { margin-top: 1.25em; margin-bottom: 1.25em; padding-left: 1.625em; color: var(--color-foreground); opacity: 0.9; }
    .doc-content li { margin-top: 0.5em; margin-bottom: 0.5em; }
    .doc-content blockquote { font-weight: 400; font-style: italic; border-left: 0.25rem solid var(--color-border); margin: 1.5em 0; padding-left: 1em; color: var(--color-muted-foreground); }
    .doc-content code { background: var(--color-muted); padding: 0.2em 0.4em; border-radius: 0.25rem; font-size: 0.875em; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; color: var(--color-foreground); }
    .doc-content pre { background: var(--code-bg); padding: 1.25rem; border-radius: var(--radius); overflow-x: auto; border: 1px solid var(--color-border); margin: 1.5em 0; }
    .doc-content pre code { background: transparent; padding: 0; border-radius: 0; font-size: 0.875em; color: var(--code-text); }
    .doc-content table { width: 100%; text-align: left; border-collapse: collapse; margin: 2em 0; font-size: 0.875rem; }
    .doc-content th { font-weight: 600; border-bottom: 1px solid var(--color-border); padding: 0.75em 1em; color: var(--color-foreground); background: var(--color-muted); }
    .doc-content td { border-bottom: 1px solid var(--color-border); padding: 0.75em 1em; color: var(--color-foreground); opacity: 0.9; }
    .doc-content tr:nth-child(even) td { background: var(--table-stripe); }
    .doc-content hr { border-color: var(--color-border); border-width: 1px; margin: 3em 0; }
    ::-webkit-scrollbar { width: 8px; height: 8px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: var(--color-border); border-radius: 4px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--color-muted-foreground); }
    .progress-container { position: absolute; top: 0; left: 0; width: 100%; height: 3px; background: transparent; z-index: 20; }
    .progress-bar { height: 100%; background: var(--color-primary); width: 0%; transition: width 0.1s; }
    .toc-container { display: none; position: fixed; top: 80px; right: 20px; width: 260px; max-height: calc(100vh - 100px); overflow-y: auto; background: var(--color-background); border: 1px solid var(--color-border); border-radius: var(--radius); padding: 1rem; font-size: 0.875rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); z-index: 5; }
    @media (min-width: 1200px) { .toc-container.active { display: block; } }
    .toc-container h3 { margin-top: 0; font-size: 1rem; margin-bottom: 0.75rem; font-weight: 600; }
    .toc-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.5rem; }
    .toc-link { color: var(--color-muted-foreground); text-decoration: none; display: block; line-height: 1.3; transition: color 0.2s; }
    .toc-link:hover { color: var(--color-foreground); }
    .toc-h2 { font-weight: 500; }
    .toc-h3 { margin-left: 1rem; font-size: 0.8rem; }
    .doc-content pre { position: relative; }
    .copy-btn { position: absolute; top: 0.5rem; right: 0.5rem; background: var(--color-muted); color: var(--color-foreground); border: 1px solid var(--color-border); border-radius: var(--radius); padding: 0.25rem 0.5rem; font-size: 0.75rem; cursor: pointer; opacity: 0; transition: all 0.2s; font-family: inherit; }
    .doc-content pre:hover .copy-btn { opacity: 1; }
    .copy-btn:hover { background: var(--color-secondary); }
  </style>
</head>
<body>
  <div class="layout">
    <aside>
      <div class="aside-header">
        <div class="header-top">
          <div class="brand">
            <!-- Se houver logo no projeto, coloque-a como logo-beesy.svg ou similar e referencie aqui -->
            <img src="logo-beesy.svg" alt="Logo" onerror="this.style.display='none'" />
            <h2>Documentação</h2>
          </div>
          <div class="header-actions">
            <button type="button" class="icon-btn text-btn small" id="btnDecreaseFont" aria-label="Diminuir fonte" title="Diminuir fonte">A-</button>
            <button type="button" class="icon-btn text-btn large" id="btnIncreaseFont" aria-label="Aumentar fonte" title="Aumentar fonte">A+</button>
            <button type="button" class="icon-btn" id="themeToggle" aria-label="Alternar Tema" title="Alternar Tema" style="width: 34px; height: 34px; padding: 0;">
              <svg id="moonIcon" viewBox="0 0 24 24" style="display:none;"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
              <svg id="sunIcon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
            </button>
          </div>
        </div>
        <input type="text" id="docFilter" class="filter-input" placeholder="Localizar documento..." aria-label="Filtrar documentos" />
      </div>
      <div class="aside-nav"><ul>${menuItems}</ul></div>
    </aside>
    <main>
      <div class="progress-container"><div class="progress-bar" id="progressBar"></div></div>
      <div class="toolbar" id="toolbar" style="display:none;">
        <button type="button" class="btn" id="btnExport" aria-label="Exportar Documento">
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
          Exportar Markdown
        </button>
      </div>
      <div class="content-wrapper" id="contentWrapper">
        <p class="placeholder" id="placeholder">Selecione um documento no menu lateral para visualizar.</p>
        <div id="docContainer"></div>
      </div>
      <div id="tocContainer" class="toc-container">
        <h3>Nesta página</h3>
        <ul id="tocList" class="toc-list"></ul>
      </div>
    </main>
  </div>  <script>
    const DOCS = ${embeddedJson};
    const container = document.getElementById('docContainer');
    const placeholder = document.getElementById('placeholder');
    const toolbar = document.getElementById('toolbar');
    const btnExport = document.getElementById('btnExport');
    const contentWrapper = document.getElementById('contentWrapper');
    const filterInput = document.getElementById('docFilter');
    const themeToggle = document.getElementById('themeToggle');
    const btnDecreaseFont = document.getElementById('btnDecreaseFont');
    const btnIncreaseFont = document.getElementById('btnIncreaseFont');
    let currentFile = null;

    let currentFontSize = parseFloat(localStorage.getItem('docs-font-size')) || 1;
    function updateFontSize() {
      document.documentElement.style.setProperty('--doc-font-size', currentFontSize + 'rem');
      localStorage.setItem('docs-font-size', currentFontSize);
    }
    updateFontSize();
    if (btnDecreaseFont && btnIncreaseFont) {
      btnDecreaseFont.addEventListener('click', () => { if (currentFontSize > 0.8) { currentFontSize -= 0.1; updateFontSize(); } });
      btnIncreaseFont.addEventListener('click', () => { if (currentFontSize < 1.6) { currentFontSize += 0.1; updateFontSize(); } });
    }

    function setTheme(theme) {
      document.documentElement.className = theme;
      localStorage.setItem('docs-theme', theme);
      document.getElementById('moonIcon').style.display = theme === 'light' ? 'block' : 'none';
      document.getElementById('sunIcon').style.display = theme === 'dark' ? 'block' : 'none';
    }
    
    const savedTheme = localStorage.getItem('docs-theme') || 'dark';
    setTheme(savedTheme);

    themeToggle.addEventListener('click', () => {
      setTheme(document.documentElement.className === 'light' ? 'dark' : 'light');
    });

    filterInput.addEventListener('input', function(e) {
      const term = e.target.value.toLowerCase();
      document.querySelectorAll('.doc-link').forEach(function(btn) {
        const text = btn.textContent.toLowerCase();
        btn.parentElement.style.display = text.includes(term) ? '' : 'none';
      });
    });

    function renderDoc(file) {
      const raw = DOCS[file];
      if (!raw) return;
      currentFile = file;
      placeholder.style.display = 'none';
      toolbar.style.display = 'flex';
      
      document.querySelectorAll('.doc-link').forEach(function (el) {
        el.classList.toggle('active', el.getAttribute('data-file') === file);
      });
      
      const div = document.createElement('div');
      div.className = 'doc-content active';
      div.id = 'content-' + file.replace(/\./g, '-');
      div.innerHTML = marked.parse(raw);
      
      div.querySelectorAll('pre').forEach(pre => {
        const btn = document.createElement('button');
        btn.className = 'copy-btn';
        btn.textContent = 'Copiar';
        btn.addEventListener('click', () => {
          const code = pre.querySelector('code')?.innerText || pre.innerText;
          navigator.clipboard.writeText(code).then(() => {
            btn.textContent = 'Copiado!';
            setTimeout(() => { btn.textContent = 'Copiar'; }, 2000);
          });
        });
        pre.appendChild(btn);
      });
      
      container.innerHTML = '';
      container.appendChild(div);
      contentWrapper.scrollTop = 0;
      document.getElementById('progressBar').style.width = '0%';

      const tocContainer = document.getElementById('tocContainer');
      const tocList = document.getElementById('tocList');
      if (tocContainer && tocList) {
        tocList.innerHTML = '';
        const headings = div.querySelectorAll('h2, h3');
        if (headings.length > 0) {
          tocContainer.classList.add('active');
          headings.forEach((h, index) => {
            if (!h.id) h.id = 'heading-' + index;
            const li = document.createElement('li');
            const a = document.createElement('a');
            a.href = '#' + h.id;
            a.className = 'toc-link ' + (h.tagName === 'H2' ? 'toc-h2' : 'toc-h3');
            a.textContent = h.textContent;
            a.addEventListener('click', (e) => {
              e.preventDefault();
              h.scrollIntoView({ behavior: 'smooth' });
            });
            li.appendChild(a);
            tocList.appendChild(li);
          });
        } else {
          tocContainer.classList.remove('active');
        }
      }
    }

    contentWrapper.addEventListener('scroll', () => {
      const scrollTop = contentWrapper.scrollTop;
      const scrollHeight = contentWrapper.scrollHeight - contentWrapper.clientHeight;
      const progress = scrollHeight > 0 ? (scrollTop / scrollHeight) * 100 : 0;
      document.getElementById('progressBar').style.width = progress + '%';
    });

    document.querySelectorAll('.doc-link').forEach(function (btn) {
      btn.addEventListener('click', function () {
        renderDoc(this.getAttribute('data-file'));
      });
    });

    btnExport.addEventListener('click', function () {
      if (!currentFile || !DOCS[currentFile]) return;
      const blob = new Blob([DOCS[currentFile]], { type: 'text/markdown;charset=utf-8' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = currentFile;
      a.click();
      URL.revokeObjectURL(a.href);
    });
  </script>
</body>
</html>`;
  fs.writeFileSync(OUTPUT_PATH, html, 'utf8');
  console.log('Generated:', OUTPUT_PATH);
}
main();