/**
 * Ebook Reader - Reader Engine using epub.js with in-memory ArrayBuffer loading
 */

document.addEventListener('DOMContentLoaded', async () => {
    const config = window.BOOK_CONFIG;
    if (!config || !config.url) {
        console.error("Book config missing.");
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // =========================================================================
    // UI Elements
    // =========================================================================
    const viewerEl = document.getElementById('viewer');
    const loadingEl = document.getElementById('readerLoading');
    const prevZone = document.getElementById('prevZone');
    const nextZone = document.getElementById('nextZone');
    const headerEl = document.getElementById('readerHeader');
    const footerEl = document.getElementById('readerFooter');
    const tocSidebar = document.getElementById('tocSidebar');
    const tocBackdrop = document.getElementById('tocBackdrop');
    const tocToggleBtn = document.getElementById('tocToggleBtn');
    const tocCloseBtn = document.getElementById('tocCloseBtn');
    const tocContent = document.getElementById('tocContent');
    const settingsToggleBtn = document.getElementById('settingsToggleBtn');
    const settingsPanel = document.getElementById('settingsPanel');
    const fullscreenBtn = document.getElementById('fullscreenBtn');
    const fullscreenIconExpand = document.getElementById('fullscreenIconExpand');
    const fullscreenIconCompress = document.getElementById('fullscreenIconCompress');
    const readingProgressBar = document.getElementById('readingProgressBar');
    const readingProgressText = document.getElementById('readingProgressText');
    const currentChapterTitle = document.getElementById('currentChapterTitle');
    const syncStatus = document.getElementById('syncStatus');
    const fontSizeDisplay = document.getElementById('fontSizeDisplay');
    const fontDecreaseBtn = document.getElementById('fontDecreaseBtn');
    const fontIncreaseBtn = document.getElementById('fontIncreaseBtn');

    // =========================================================================
    // User Preferences (LocalStorage)
    // =========================================================================
    let currentTheme = localStorage.getItem('epub_theme') || localStorage.getItem('app_theme') || 'light';
    let currentFontSize = parseInt(localStorage.getItem('epub_font_size') || '100', 10);
    let currentFontFamily = localStorage.getItem('epub_font_family') || 'serif';
    let currentFlow = localStorage.getItem('epub_flow') || 'paginated'; // 'paginated' or 'scrolled'

    const fontFamilies = {
        'serif': "'Merriweather', 'Lora', Georgia, serif",
        'sans-serif': "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif"
    };

    // Helper for error state in UI
    function showError(message) {
        if (loadingEl) {
            loadingEl.classList.remove('fade-out');
            loadingEl.innerHTML = `
                <div style="text-align: center; max-width: 420px; padding: 2rem; background: var(--reader-ui-bg); border-radius: 12px; border: 1px solid var(--reader-ui-border); box-shadow: var(--shadow-ui);">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" style="margin-bottom: 1rem;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--reader-ui-text);">Buch konnte nicht geladen werden</h3>
                    <p style="font-size: 0.875rem; color: var(--reader-ui-muted); margin-bottom: 1.5rem; line-height: 1.5;">${message}</p>
                    <a href="index.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1.25rem; background: #2563eb; color: #ffffff; border-radius: 8px; text-decoration: none; font-size: 0.875rem; font-weight: 500;">
                        Zurück zur Bibliothek
                    </a>
                </div>
            `;
        }
    }

    // =========================================================================
    // Fetch EPUB as ArrayBuffer & Initialize epub.js
    // =========================================================================
    let book, rendition;

    try {
        if (loadingEl) {
            const p = loadingEl.querySelector('p');
            if (p) p.textContent = 'Buch wird geladen...';
        }

        const response = await fetch(config.url, { credentials: 'same-origin' });
        if (!response.ok) {
            throw new Error(`Server antwortete mit Status ${response.status} (${response.statusText})`);
        }

        const arrayBuffer = await response.arrayBuffer();
        if (!arrayBuffer || arrayBuffer.byteLength < 50) {
            throw new Error("Empfangene Buchdatei ist leer oder unvollständig.");
        }

        // Initialize ePub directly with the downloaded ArrayBuffer
        book = ePub(arrayBuffer);
        rendition = book.renderTo(viewerEl, {
            width: '100%',
            height: '100%',
            spread: 'none',
            flow: currentFlow === 'scrolled' ? 'scrolled' : 'paginated',
            manager: 'continuous'
        });

    } catch (err) {
        console.error("Fehler beim Laden des EPUBs:", err);
        showError(err.message || "Unbekannter Fehler beim Laden.");
        return;
    }

    // =========================================================================
    // Themes & Fonts
    // =========================================================================
    function registerThemes() {
        rendition.themes.register('light', {
            'body': {
                'color': '#1a1a1a !important',
                'background': '#ffffff !important',
                'font-family': fontFamilies[currentFontFamily] + ' !important',
                'line-height': '1.7 !important',
                'padding': '0 24px !important'
            },
            'p, div, span, li': {
                'color': '#1a1a1a !important'
            },
            'h1, h2, h3, h4, h5, h6': {
                'color': '#0f172a !important',
                'font-family': fontFamilies[currentFontFamily] + ' !important'
            },
            'a': {
                'color': '#2563eb !important'
            }
        });

        rendition.themes.register('sepia', {
            'body': {
                'color': '#533f2d !important',
                'background': '#f8f1e3 !important',
                'font-family': fontFamilies[currentFontFamily] + ' !important',
                'line-height': '1.7 !important',
                'padding': '0 24px !important'
            },
            'p, div, span, li': {
                'color': '#533f2d !important'
            },
            'h1, h2, h3, h4, h5, h6': {
                'color': '#3f2e1e !important',
                'font-family': fontFamilies[currentFontFamily] + ' !important'
            },
            'a': {
                'color': '#92400e !important'
            }
        });

        rendition.themes.register('dark', {
            'body': {
                'color': '#e4e4e7 !important',
                'background': '#121214 !important',
                'font-family': fontFamilies[currentFontFamily] + ' !important',
                'line-height': '1.7 !important',
                'padding': '0 24px !important'
            },
            'p, div, span, li': {
                'color': '#e4e4e7 !important'
            },
            'h1, h2, h3, h4, h5, h6': {
                'color': '#f4f4f5 !important',
                'font-family': fontFamilies[currentFontFamily] + ' !important'
            },
            'a': {
                'color': '#60a5fa !important'
            }
        });
    }

    function applyTheme(theme) {
        currentTheme = theme;
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('epub_theme', theme);
        localStorage.setItem('app_theme', theme === 'dark' ? 'dark' : 'light');
        rendition.themes.select(theme);

        document.querySelectorAll('.theme-btn').forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-theme') === theme);
        });
    }

    function applyFontSize(size) {
        currentFontSize = Math.min(180, Math.max(70, size));
        if (fontSizeDisplay) fontSizeDisplay.textContent = currentFontSize + '%';
        localStorage.setItem('epub_font_size', currentFontSize.toString());
        rendition.themes.fontSize(currentFontSize + '%');
    }

    function applyFontFamily(font) {
        currentFontFamily = font;
        localStorage.setItem('epub_font_family', font);
        registerThemes();
        rendition.themes.select(currentTheme);

        document.querySelectorAll('.font-family-btn').forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-font') === font);
        });
    }

    function applyFlow(flow) {
        currentFlow = flow;
        localStorage.setItem('epub_flow', flow);

        document.body.classList.toggle('mode-scrolled', flow === 'scrolled');
        document.body.classList.toggle('mode-paginated', flow === 'paginated');

        document.querySelectorAll('.flow-btn').forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-flow') === flow);
        });

        if (rendition) {
            rendition.flow(flow === 'scrolled' ? 'scrolled' : 'paginated');
            setTimeout(setupScrollObserver, 300);
        }
    }

    // Scroll observer to seamlessly load subsequent chapters when scrolling near bottom
    let isFetchingNextChapter = false;
    function setupScrollObserver() {
        const scroller = viewerEl.querySelector('.epub-container') || viewerEl;
        if (!scroller || scroller._hasScrollObserver) return;
        scroller._hasScrollObserver = true;

        scroller.addEventListener('scroll', () => {
            if (currentFlow !== 'scrolled' || isFetchingNextChapter) return;

            const remaining = scroller.scrollHeight - (scroller.scrollTop + scroller.clientHeight);
            if (remaining < 450) {
                isFetchingNextChapter = true;
                rendition.next().then(() => {
                    setTimeout(() => { isFetchingNextChapter = false; }, 400);
                }).catch(() => {
                    isFetchingNextChapter = false;
                });
            }
        }, { passive: true });
    }

    registerThemes();
    applyTheme(currentTheme);
    applyFontSize(currentFontSize);
    applyFontFamily(currentFontFamily);
    applyFlow(currentFlow);

    // Initial Display (jump to saved CFI if available, else start at beginning)
    const initialTarget = config.initialCfi || undefined;
    rendition.display(initialTarget).catch(() => {
        rendition.display();
    });

    rendition.on('rendered', () => {
        setupScrollObserver();
        if (loadingEl) {
            loadingEl.classList.add('fade-out');
            setTimeout(() => loadingEl.remove(), 400);
        }
    });

    // =========================================================================
    // Locations Generation & Reading Progress
    // =========================================================================
    let locationsReady = false;

    book.ready.then(() => {
        return book.locations.generate(1024);
    }).then(() => {
        locationsReady = true;
        const currentLoc = rendition.currentLocation();
        if (currentLoc && currentLoc.start) {
            updateProgressDisplay(currentLoc.start.cfi);
        }
    }).catch(err => {
        console.warn("Locations generation note:", err);
    });

    // =========================================================================
    // Debounced Progress Auto-Save
    // =========================================================================
    let saveTimeout = null;
    let lastSavedCfi = config.initialCfi || '';

    function updateProgressDisplay(cfi) {
        if (!cfi) return;

        let percent = config.initialPercent || 0;
        if (locationsReady && book.locations) {
            const calculated = book.locations.percentageFromCfi(cfi) * 100;
            if (!isNaN(calculated) && calculated >= 0) {
                percent = Math.min(100, Math.max(0, calculated));
            }
        }

        if (readingProgressBar) readingProgressBar.style.width = percent.toFixed(1) + '%';
        if (readingProgressText) readingProgressText.textContent = Math.round(percent) + '%';

        highlightActiveTocChapter(cfi);
        return percent;
    }

    function scheduleSaveProgress(cfi) {
        if (!cfi || cfi === lastSavedCfi) return;

        const percent = updateProgressDisplay(cfi);

        if (syncStatus) {
            syncStatus.className = 'sync-status syncing';
            syncStatus.querySelector('.sync-label').textContent = 'Speichert...';
        }

        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(async () => {
            try {
                const response = await fetch('api/save_progress.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        book_id: config.id,
                        cfi: cfi,
                        progress_percent: percent,
                        csrf_token: csrfToken
                    })
                });

                const data = await response.json();
                if (response.ok && data.success) {
                    lastSavedCfi = cfi;
                    if (syncStatus) {
                        syncStatus.className = 'sync-status';
                        syncStatus.querySelector('.sync-label').textContent = 'Gespeichert';
                    }
                } else {
                    throw new Error(data.error || 'Server error');
                }
            } catch (err) {
                console.error("Fehler beim Speichern des Fortschritts:", err);
                if (syncStatus) {
                    syncStatus.className = 'sync-status error';
                    syncStatus.querySelector('.sync-label').textContent = 'Speicherfehler';
                }
            }
        }, 800);
    }

    rendition.on('relocated', (location) => {
        if (!location || !location.start) return;
        const cfi = location.start.cfi;
        scheduleSaveProgress(cfi);
    });

    // =========================================================================
    // Table of Contents (TOC)
    // =========================================================================
    let tocItems = [];

    book.loaded.navigation.then((nav) => {
        tocItems = nav.toc || [];
        renderToc(tocItems, tocContent);
    });

    function renderToc(items, container) {
        if (!container) return;
        if (!items || items.length === 0) {
            container.innerHTML = '<div class="toc-empty">Kein Inhaltsverzeichnis verfügbar.</div>';
            return;
        }

        const ul = document.createElement('ul');
        ul.className = 'toc-list';

        function buildList(listItems, parentUl) {
            listItems.forEach(item => {
                const li = document.createElement('li');
                li.className = 'toc-item';

                const a = document.createElement('a');
                a.href = item.href;
                a.className = 'toc-link';
                a.textContent = item.label ? item.label.trim() : 'Kapitel';
                a.setAttribute('data-href', item.href);

                a.addEventListener('click', (e) => {
                    e.preventDefault();
                    rendition.display(item.href);
                    closeToc();
                });

                li.appendChild(a);

                if (item.subitems && item.subitems.length > 0) {
                    const subUl = document.createElement('ul');
                    subUl.className = 'toc-sublist';
                    buildList(item.subitems, subUl);
                    li.appendChild(subUl);
                }

                parentUl.appendChild(li);
            });
        }

        buildList(items, ul);
        container.innerHTML = '';
        container.appendChild(ul);
    }

    function highlightActiveTocChapter(cfi) {
        if (!tocItems || tocItems.length === 0) return;
        try {
            const item = book.navigation.get(cfi);
            if (item && item.label) {
                if (currentChapterTitle) currentChapterTitle.textContent = item.label.trim();
                document.querySelectorAll('.toc-link').forEach(link => {
                    link.classList.toggle('active', link.getAttribute('data-href') === item.href);
                });
            }
        } catch {
            // Ignore
        }
    }

    function openToc() {
        tocSidebar?.classList.add('open');
        tocBackdrop?.classList.add('open');
        settingsPanel?.classList.add('hidden');
    }

    function closeToc() {
        tocSidebar?.classList.remove('open');
        tocBackdrop?.classList.remove('open');
    }

    tocToggleBtn?.addEventListener('click', () => {
        if (tocSidebar?.classList.contains('open')) {
            closeToc();
        } else {
            openToc();
        }
    });

    tocCloseBtn?.addEventListener('click', closeToc);
    tocBackdrop?.addEventListener('click', closeToc);

    // =========================================================================
    // Navigation (Edge click zones, keyboard, gestures)
    // =========================================================================
    prevZone?.addEventListener('click', (e) => {
        e.stopPropagation();
        rendition.prev();
    });

    nextZone?.addEventListener('click', (e) => {
        e.stopPropagation();
        rendition.next();
    });

    function handleKeyDown(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

        switch (e.key) {
            case 'ArrowLeft':
            case 'PageUp':
            case 'h':
                rendition.prev();
                break;
            case 'ArrowRight':
            case 'PageDown':
            case 'Space':
            case 'l':
                rendition.next();
                break;
            case 'f':
            case 'F':
                toggleFullscreen();
                break;
            case 'Escape':
                closeToc();
                settingsPanel?.classList.add('hidden');
                break;
        }
    }

    window.addEventListener('keydown', handleKeyDown);

    rendition.on('rendered', (section, view) => {
        if (view && view.document) {
            view.document.addEventListener('keydown', handleKeyDown);
            setupTouchGestures(view.document);
        }
    });

    function setupTouchGestures(targetElement) {
        let touchStartX = 0;
        let touchStartY = 0;
        let touchStartTime = 0;

        targetElement.addEventListener('touchstart', (e) => {
            if (e.touches.length === 1) {
                touchStartX = e.touches[0].clientX;
                touchStartY = e.touches[0].clientY;
                touchStartTime = Date.now();
            }
        }, { passive: true });

        targetElement.addEventListener('touchend', (e) => {
            if (e.changedTouches.length === 1) {
                const touchEndX = e.changedTouches[0].clientX;
                const touchEndY = e.changedTouches[0].clientY;
                const dx = touchEndX - touchStartX;
                const dy = touchEndY - touchStartY;
                const timeDiff = Date.now() - touchStartTime;

                if (currentFlow === 'paginated' && timeDiff < 500 && Math.abs(dx) > 45 && Math.abs(dy) < Math.abs(dx) * 0.8) {
                    if (dx < 0) {
                        rendition.next();
                    } else {
                        rendition.prev();
                    }
                } else if (Math.abs(dx) < 15 && Math.abs(dy) < 15 && timeDiff < 300) {
                    const width = window.innerWidth;
                    if (touchStartX > width * 0.25 && touchStartX < width * 0.75) {
                        toggleUiBars();
                    }
                }
            }
        }, { passive: true });
    }

    setupTouchGestures(viewerEl);

    // =========================================================================
    // UI Bar Visibility (Distraction-Free Reading Mode)
    // =========================================================================
    let uiVisible = true;

    function toggleUiBars() {
        uiVisible = !uiVisible;
        headerEl?.classList.toggle('header-hidden', !uiVisible);
        footerEl?.classList.toggle('footer-hidden', !uiVisible);
        document.body.classList.toggle('fullscreen-ui', !uiVisible);
        if (!uiVisible) {
            settingsPanel?.classList.add('hidden');
        }
    }

    // =========================================================================
    // Settings Panel Controls
    // =========================================================================
    settingsToggleBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        settingsPanel?.classList.toggle('hidden');
        closeToc();
    });

    document.addEventListener('click', (e) => {
        if (settingsPanel && !settingsPanel.contains(e.target) && e.target !== settingsToggleBtn && !settingsToggleBtn.contains(e.target)) {
            settingsPanel.classList.add('hidden');
        }
    });

    document.querySelectorAll('.theme-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const theme = btn.getAttribute('data-theme');
            if (theme) applyTheme(theme);
        });
    });

    fontDecreaseBtn?.addEventListener('click', () => {
        applyFontSize(currentFontSize - 10);
    });

    fontIncreaseBtn?.addEventListener('click', () => {
        applyFontSize(currentFontSize + 10);
    });

    document.querySelectorAll('.font-family-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const font = btn.getAttribute('data-font');
            if (font) applyFontFamily(font);
        });
    });

    document.querySelectorAll('.flow-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const flow = btn.getAttribute('data-flow');
            if (flow) applyFlow(flow);
        });
    });

    // =========================================================================
    // Fullscreen Toggle
    // =========================================================================
    function toggleFullscreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(err => {
                console.warn("Fullscreen error:", err);
            });
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            }
        }
    }

    fullscreenBtn?.addEventListener('click', toggleFullscreen);

    document.addEventListener('fullscreenchange', () => {
        const isFs = !!document.fullscreenElement;
        fullscreenIconExpand?.classList.toggle('hidden', isFs);
        fullscreenIconCompress?.classList.toggle('hidden', !isFs);
    });
});
