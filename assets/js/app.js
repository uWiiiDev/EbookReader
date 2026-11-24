/**
 * Ebook Reader - Library / Dashboard JavaScript
 */

document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // =========================================================================
    // Theme Toggle (Dark / Light)
    // =========================================================================
    const themeToggleBtn = document.getElementById('themeToggleBtn');
    const sunIcon = themeToggleBtn?.querySelector('.theme-sun-icon');
    const moonIcon = themeToggleBtn?.querySelector('.theme-moon-icon');

    function updateThemeIcons(theme) {
        if (theme === 'dark') {
            sunIcon?.classList.remove('hidden');
            moonIcon?.classList.add('hidden');
        } else {
            sunIcon?.classList.add('hidden');
            moonIcon?.classList.remove('hidden');
        }
    }

    const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
    updateThemeIcons(currentTheme);

    themeToggleBtn?.addEventListener('click', () => {
        const nextTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', nextTheme);
        localStorage.setItem('app_theme', nextTheme);
        localStorage.setItem('epub_theme', nextTheme);
        updateThemeIcons(nextTheme);
    });

    // =========================================================================
    // Toast Notifications
    // =========================================================================
    const toastContainer = document.getElementById('toastContainer');

    function showToast(message, type = 'info') {
        if (!toastContainer) return;
        const toast = document.createElement('div');
        toast.className = `toast ${type === 'error' ? 'toast-error' : type === 'success' ? 'toast-success' : ''}`;
        toast.textContent = message;
        toastContainer.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(8px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    // =========================================================================
    // Realtime Search Filter
    // =========================================================================
    const searchInput = document.getElementById('searchInput');
    const searchClear = document.getElementById('searchClear');
    const booksGrid = document.getElementById('booksGrid');
    const emptyState = document.getElementById('emptyState');

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim().toLowerCase();

            if (query.length > 0) {
                searchClear?.classList.remove('hidden');
            } else {
                searchClear?.classList.add('hidden');
            }

            filterBooks(query);
        });

        searchClear?.addEventListener('click', () => {
            searchInput.value = '';
            searchClear.classList.add('hidden');
            filterBooks('');
            searchInput.focus();
        });
    }

    function filterBooks(query) {
        if (!booksGrid) return;
        const cards = booksGrid.querySelectorAll('.book-card');
        let visibleCount = 0;

        cards.forEach(card => {
            const title = card.getAttribute('data-title') || '';
            const author = card.getAttribute('data-author') || '';

            if (query === '' || title.includes(query) || author.includes(query)) {
                card.classList.remove('hidden');
                visibleCount++;
            } else {
                card.classList.add('hidden');
            }
        });

        if (emptyState) {
            if (visibleCount === 0) {
                emptyState.classList.remove('hidden');
            } else {
                emptyState.classList.add('hidden');
            }
        }
    }

    // =========================================================================
    // Drag & Drop / File Upload
    // =========================================================================
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('fileInput');
    const browseBtn = document.getElementById('browseBtn');
    const progressContainer = document.getElementById('uploadProgressContainer');
    const progressBar = document.getElementById('uploadProgressBar');
    const progressPercent = document.getElementById('uploadPercent');
    const uploadFilename = document.getElementById('uploadFilename');
    const dropzoneContent = dropzone?.querySelector('.dropzone-content');

    if (dropzone && fileInput) {
        browseBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            fileInput.click();
        });

        dropzone.addEventListener('click', () => {
            fileInput.click();
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('dragover');
            }, false);
        });

        dropzone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files && files.length > 0) {
                handleFileUpload(files[0]);
            }
        });

        fileInput.addEventListener('change', () => {
            if (fileInput.files && fileInput.files.length > 0) {
                handleFileUpload(fileInput.files[0]);
            }
        });
    }

    function handleFileUpload(file) {
        if (!file.name.toLowerCase().endsWith('.epub')) {
            showToast('Bitte wählen Sie eine gültige .epub Datei aus.', 'error');
            return;
        }

        // Show progress UI
        dropzoneContent?.classList.add('hidden');
        progressContainer?.classList.remove('hidden');
        if (uploadFilename) uploadFilename.textContent = file.name;
        if (progressBar) progressBar.style.width = '0%';
        if (progressPercent) progressPercent.textContent = '0%';

        const formData = new FormData();
        formData.append('epub', file);
        formData.append('csrf_token', csrfToken);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'api/upload.php', true);
        xhr.setRequestHeader('X-CSRF-Token', csrfToken);

        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 95); // leave 5% for server processing
                if (progressBar) progressBar.style.width = percent + '%';
                if (progressPercent) progressPercent.textContent = percent + '%';
            }
        });

        xhr.onload = () => {
            if (progressBar) progressBar.style.width = '100%';
            if (progressPercent) progressPercent.textContent = '100%';

            setTimeout(() => {
                dropzoneContent?.classList.remove('hidden');
                progressContainer?.classList.add('hidden');
                fileInput.value = '';

                try {
                    const response = JSON.parse(xhr.responseText);
                    if (xhr.status === 200 && response.success) {
                        showToast(response.message || 'Buch erfolgreich hinzugefügt!', 'success');
                        // Smoothly refresh after brief delay
                        setTimeout(() => {
                            window.location.reload();
                        }, 600);
                    } else {
                        showToast(response.error || 'Fehler beim Upload.', 'error');
                    }
                } catch {
                    showToast('Unerwarteter Serverfehler beim Verarbeiten des EPUBs.', 'error');
                }
            }, 300);
        };

        xhr.onerror = () => {
            dropzoneContent?.classList.remove('hidden');
            progressContainer?.classList.add('hidden');
            fileInput.value = '';
            showToast('Netzwerkfehler während des Uploads.', 'error');
        };

        xhr.send(formData);
    }

    // =========================================================================
    // Delete Book Modal
    // =========================================================================
    const deleteModal = document.getElementById('deleteModal');
    const deleteModalTitle = document.getElementById('deleteModalTitle');
    const modalCloseBtn = document.getElementById('modalCloseBtn');
    const modalCancelBtn = document.getElementById('modalCancelBtn');
    const modalConfirmDeleteBtn = document.getElementById('modalConfirmDeleteBtn');
    const bookCountBadge = document.getElementById('bookCountBadge');
    let bookIdToDelete = null;
    let cardElementToDelete = null;

    function openDeleteModal(id, title, cardEl) {
        bookIdToDelete = id;
        cardElementToDelete = cardEl;
        if (deleteModalTitle) deleteModalTitle.textContent = `„${title}“`;
        deleteModal?.classList.remove('hidden');
    }

    function closeDeleteModal() {
        deleteModal?.classList.add('hidden');
        bookIdToDelete = null;
        cardElementToDelete = null;
    }

    modalCloseBtn?.addEventListener('click', closeDeleteModal);
    modalCancelBtn?.addEventListener('click', closeDeleteModal);
    deleteModal?.addEventListener('click', (e) => {
        if (e.target === deleteModal) closeDeleteModal();
    });

    // Listen for delete buttons on cards
    document.addEventListener('click', (e) => {
        const deleteBtn = e.target.closest('.btn-delete');
        if (deleteBtn) {
            e.preventDefault();
            e.stopPropagation();
            const id = deleteBtn.getAttribute('data-id');
            const title = deleteBtn.getAttribute('data-title');
            const card = deleteBtn.closest('.book-card');
            openDeleteModal(id, title, card);
        }
    });

    modalConfirmDeleteBtn?.addEventListener('click', async () => {
        if (!bookIdToDelete) return;

        modalConfirmDeleteBtn.disabled = true;
        modalConfirmDeleteBtn.textContent = 'Wird gelöscht...';

        try {
            const response = await fetch('api/delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ id: bookIdToDelete, csrf_token: csrfToken })
            });

            const result = await response.json();

            if (response.ok && result.success) {
                showToast(result.message || 'Buch gelöscht.', 'success');
                if (cardElementToDelete) {
                    cardElementToDelete.style.transform = 'scale(0.9)';
                    cardElementToDelete.style.opacity = '0';
                    cardElementToDelete.style.transition = 'all 0.3s ease';
                    setTimeout(() => {
                        cardElementToDelete.remove();
                        // Update counter
                        const remainingCards = booksGrid?.querySelectorAll('.book-card').length || 0;
                        if (bookCountBadge) {
                            bookCountBadge.textContent = `${remainingCards} ${remainingCards === 1 ? 'Buch' : 'Bücher'}`;
                        }
                        if (remainingCards === 0 && emptyState) {
                            emptyState.classList.remove('hidden');
                        }
                    }, 300);
                }
            } else {
                showToast(result.error || 'Fehler beim Löschen des Buches.', 'error');
            }
        } catch {
            showToast('Netzwerkfehler beim Löschen.', 'error');
        } finally {
            modalConfirmDeleteBtn.disabled = false;
            modalConfirmDeleteBtn.textContent = 'Löschen';
            closeDeleteModal();
        }
    });
});
