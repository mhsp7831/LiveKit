document.addEventListener('DOMContentLoaded', function() {

    // --- Toast Notification Handler ---
    const showToast = (message, type = 'success') => {
        const container = document.getElementById('toast-container');
        if (!container) return;
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        const icon = type === 'success' 
            ? '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>'
            : '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
        toast.innerHTML = `${icon}<span>${message}</span>`;
        container.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 100);
        setTimeout(() => {
            toast.classList.remove('show');
            toast.addEventListener('transitionend', () => toast.remove());
        }, 4000);
    };

    // --- Generic AJAX Requester ---
    const sendRequest = async (formData) => {
        // Automatically add CSRF token from any form on the page
        if (!formData.has('csrf_token')) {
            const token = document.querySelector('input[name="csrf_token"]')?.value;
            if (token) formData.append('csrf_token', token);
        }

        const response = await fetch('ajax-handler.php', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' },
        });
        if (!response.ok) throw new Error(`Server responded with status: ${response.status}`);
        const result = await response.json();
        if (!result.success) throw new Error(result.message || 'An unknown server error occurred.');
        return result;
    };

    // --- AJAX Form Submission Handler ---
    const handleFormSubmit = async (event) => {
        event.preventDefault();
        const form = event.target;
        const submitButton = form.querySelector('button[type="submit"]');
        if (!submitButton) return;
        
        submitButton.classList.add('loading');
        submitButton.disabled = true;

        try {
            const formData = new FormData(form);
            const result = await sendRequest(formData);
            
            submitButton.classList.remove('loading');
            submitButton.classList.add('success');
            showToast(result.message, 'success');
            
            if (form.id === 'settings-form') {
                // Clear file inputs
                form.querySelectorAll('input[type="file"]').forEach(input => {
                    input.value = '';
                });

                // **FIX 1: Robustly update URL fields with new values from the server response**
                // The server response `result.updated_images` contains a map of 
                // { input_name: new_url }. We can loop through this map.
                if (result.updated_images) {
                    for (const inputName in result.updated_images) {
                        const urlInput = form.querySelector(`input[name="${inputName}"]`);
                        if (urlInput) {
                            urlInput.value = result.updated_images[inputName];
                        }
                    }
                }
                
                // Clean up the URL
                if (window.history.replaceState) {
                    const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                    window.history.replaceState({path: cleanUrl}, '', cleanUrl);
                }
            }

            if (['add-user-form', 'edit-user-form'].includes(form.id)) {
                setTimeout(() => window.location.reload(), 1500);
            }
            if (form.id === 'restore-form') {
                setTimeout(() => window.location.reload(), 2000);
            }

        } catch (error) {
            submitButton.classList.remove('loading');
            showToast(error.message, 'error');
        } finally {
            setTimeout(() => {
                submitButton.classList.remove('success');
                submitButton.disabled = false;
            }, 2000);
        }
    };
    
    document.querySelectorAll('form').forEach(form => form.addEventListener('submit', handleFormSubmit));

    // --- Modal Logic ---
    const confirmModal = document.getElementById('confirm-modal');
    const promptModal = document.getElementById('prompt-modal');
    const editUserModal = document.getElementById('edit-user-modal');

    const setupModal = (modal) => {
        if (!modal) return { show: () => {}, hide: () => {} };
        const hide = () => modal.classList.remove('active');
        const show = () => modal.classList.add('active');
        modal.addEventListener('click', e => { if (e.target === modal) hide(); });
        modal.querySelectorAll('.modal-cancel-btn').forEach(btn => btn.addEventListener('click', hide));
        return { show, hide };
    };

    const confirmHandler = setupModal(confirmModal);
    const promptHandler = setupModal(promptModal);
    const editUserHandler = setupModal(editUserModal);
    
    const showConfirm = (message) => {
        return new Promise(resolve => {
            const confirmBtn = confirmModal.querySelector('#modal-confirm-btn');
            const cancelBtn = confirmModal.querySelector('#modal-cancel-btn');
            confirmModal.querySelector('#modal-text').textContent = message;

            const newConfirmBtn = confirmBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
            const newCancelBtn = cancelBtn.cloneNode(true);
            cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
            
            const hideAndResolve = (value) => {
                confirmHandler.hide();
                resolve(value);
            };

            newConfirmBtn.addEventListener('click', () => hideAndResolve(true), { once: true });
            newCancelBtn.addEventListener('click', () => hideAndResolve(false), { once: true });

            confirmHandler.show();
        });
    };
    
    const showPrompt = (config) => {
        return new Promise(resolve => {
            promptModal.querySelector('#prompt-title').textContent = config.title;
            promptModal.querySelector('#prompt-text').textContent = config.text;
            const input = promptModal.querySelector('#prompt-input');
            input.value = config.value || '';
            input.pattern = config.pattern || '.*';
            input.title = config.patternHint || '';
            
            const confirmBtn = promptModal.querySelector('#prompt-confirm-btn');
            const cancelBtn = promptModal.querySelector('#prompt-cancel-btn');

            const newConfirmBtn = confirmBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
            const newCancelBtn = cancelBtn.cloneNode(true);
            cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);

            const hideAndResolve = (value) => {
                promptHandler.hide();
                resolve(value);
            };

            newConfirmBtn.addEventListener('click', () => {
                if (!input.checkValidity()){
                    input.reportValidity();
                    return;
                }
                hideAndResolve(input.value.trim());
            }, { once: true });
            newCancelBtn.addEventListener('click', () => hideAndResolve(null), { once: true });

            promptHandler.show();
        });
    };


    // --- User Management Logic ---
    const usersList = document.getElementById('users-list');
    if (usersList) {
        usersList.addEventListener('click', async (e) => {
            const target = e.target.closest('button');
            if (!target) return;

            const userId = target.dataset.id;
            const username = target.dataset.username;

            if (target.classList.contains('edit-user-btn')) {
                document.getElementById('edit_user_id').value = userId;
                document.getElementById('edit_username').value = username;
                document.getElementById('edit_password').value = '';
                editUserHandler.show();
            } else if (target.classList.contains('delete-user-btn')) {
                const isSelfDelete = target.hasAttribute('data-self-delete');
                const message = isSelfDelete
                    ? `آیا از حذف حساب کاربری خودتان ("${username}") مطمئن هستید؟ این عمل غیرقابل بازگشت است و از سیستم خارج خواهید شد.`
                    : `آیا از حذف کاربر "${username}" مطمئن هستید؟`;

                const confirmed = await showConfirm(message);

                if (confirmed) {
                    const formData = new FormData();
                    formData.append('action', 'delete_user');
                    formData.append('user_id', userId);
                    try {
                        const result = await sendRequest(formData);
                        showToast(result.message, 'success');
                        if (result.self_delete) {
                            setTimeout(() => window.location.href = 'logout.php', 1500);
                        } else {
                            setTimeout(() => window.location.reload(), 1500);
                        }
                    } catch (error) {
                        showToast(error.message, 'error');
                    }
                }
            }
        });
    }

    // --- Event Management ---
    const eventSelector = document.getElementById('event-selector');
    if (eventSelector) {
        eventSelector.addEventListener('change', async () => {
            const formData = new FormData();
            formData.append('action', 'switch_event');
            formData.append('event_id', eventSelector.value);
            try {
                await sendRequest(formData);
                window.location.reload();
            } catch (error) { showToast(error.message, 'error'); }
        });

        document.getElementById('create-event-btn')?.addEventListener('click', async () => {
            const name = await showPrompt({
                title: 'ساخت رویداد جدید',
                text: 'یک نام برای رویداد جدید خود وارد کنید:'
            });
            if (!name) return;
            const formData = new FormData();
            formData.append('action', 'create_event');
            formData.append('event_name', name);
            try {
                await sendRequest(formData);
                window.location.reload(); 
            } catch (error) { showToast(error.message, 'error'); }
        });

        document.getElementById('rename-event-btn')?.addEventListener('click', async () => {
            const currentName = eventSelector.options[eventSelector.selectedIndex].text;
            const newName = await showPrompt({
                title: 'تغییر نام رویداد',
                text: `نام جدیدی برای "${currentName}" وارد کنید:`,
                value: currentName
            });
            if (!newName || newName === currentName) return;
            const formData = new FormData();
            formData.append('action', 'rename_event');
            formData.append('event_id', eventSelector.value);
            formData.append('event_name', newName);
            try {
                await sendRequest(formData);
                showToast('نام رویداد با موفقیت تغییر کرد.', 'success');
                eventSelector.options[eventSelector.selectedIndex].text = newName;
            } catch (error) { showToast(error.message, 'error'); }
        });
        
        document.getElementById('edit-event-id-btn')?.addEventListener('click', async () => {
            const currentId = eventSelector.value;
            const newId = await showPrompt({
                title: 'ویرایش شناسه رویداد',
                text: 'شناسه جدید باید منحصر به فرد باشد.',
                value: currentId,
                pattern: '^[a-zA-Z0-9_]+$',
                patternHint: 'فقط حروف انگلیسی، اعداد و آندرلاین (_) مجاز است.'
            });
            if (!newId || newId === currentId) return;

            const formData = new FormData();
            formData.append('action', 'edit_event_id');
            formData.append('current_event_id', currentId);
            formData.append('new_event_id', newId);

            try {
                const result = await sendRequest(formData);
                showToast(result.message, 'success');
                setTimeout(() => window.location.reload(), 1500);
            } catch (error) { showToast(error.message, 'error'); }
        });

        document.getElementById('delete-event-btn')?.addEventListener('click', async () => {
            const eventName = eventSelector.options[eventSelector.selectedIndex].text;
            const confirmed = await showConfirm(`آیا از حذف رویداد "${eventName}" مطمئن هستید؟ این عمل غیرقابل بازگشت است.`);
            if (confirmed) {
                const formData = new FormData();
                formData.append('action', 'delete_event');
                formData.append('event_id', eventSelector.value);
                try {
                    await sendRequest(formData);
                    window.location.reload();
                } catch (error) { showToast(error.message, 'error'); }
            }
        });

        document.getElementById('copy-event-id-btn')?.addEventListener('click', () => {
            const eventId = document.querySelector('.event-id-display code').textContent;
            navigator.clipboard.writeText(eventId)
                .then(() => showToast('شناسه رویداد کپی شد!', 'success'))
                .catch(() => showToast('خطا در کپی کردن شناسه.', 'error'));
        });
    }


    // --- Subtitle Management ---
    const setupSubtitleItem = (item) => {
        item.querySelector('.remove-btn')?.addEventListener('click', async () => {
            const confirmed = await showConfirm('آیا از حذف این زیرنویس مطمئن هستید؟');
            if (confirmed) item.remove();
        });
    };
    document.querySelectorAll('.subtitle-item').forEach(setupSubtitleItem);

    document.getElementById('add-subtitle-btn')?.addEventListener('click', function() {
        const container = document.getElementById('subtitles-container');
        const newItem = document.createElement('div');
        newItem.className = 'subtitle-item';
        newItem.innerHTML = `<svg class="drag-handle" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M5 3a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V5a2 2 0 00-2-2H5zm0 2h10v10H5V5zm2 1a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1zm0 4a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1zm0 4a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1z" /></svg>
                             <input type="text" name="subtitle_text[]" placeholder="متن زیرنویس">
                             <input type="url" name="subtitle_link[]" placeholder="لینک (اختیاری)">
                             <button type="button" class="remove-btn"><span class="btn-text">حذف</span></button>`;
        setupSubtitleItem(newItem);
        container.appendChild(newItem);
    });
    
    document.getElementById('remove-all-subtitles-btn')?.addEventListener('click', async () => {
        const container = document.getElementById('subtitles-container');
        if (container.children.length > 0) {
            const confirmed = await showConfirm('آیا از حذف تمام زیرنویس‌ها مطمئن هستید؟');
            if (confirmed) container.innerHTML = '';
        }
    });

    const subtitlesContainer = document.getElementById('subtitles-container');
    if (subtitlesContainer) {
        new Sortable(subtitlesContainer, { animation: 150, ghostClass: 'sortable-ghost', handle: '.drag-handle' });
    }

    // --- Tab Switching ---
    document.querySelectorAll('.tab-button').forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            document.querySelector('.tab-button.active')?.classList.remove('active');
            document.querySelector('.tab-panel.active')?.classList.remove('active');
            button.classList.add('active');
            document.getElementById(button.dataset.tab)?.classList.add('active');
        });
    });

    // --- Colors Preview ---
    const colorForm = document.getElementById('color-form');
    if (colorForm) {
        colorForm.querySelectorAll('input[type="color"]').forEach(input => {
            input.addEventListener('input', (e) => {
                let varName = e.target.name.replace('-color', '');
                document.querySelector('.color-preview-area')?.style.setProperty('--' + varName, e.target.value);
            });
        });
        document.getElementById('reset-colors-btn')?.addEventListener('click', (e) => {
            const defaults = JSON.parse(e.currentTarget.dataset.defaults);
            for (const key in defaults) {
                const inputName = key.replace(/_/g, '-') === 'title' ? 'title-color' : key.replace(/_/g, '-');
                const input = colorForm.querySelector(`input[name="${inputName}"]`);
                if (input) {
                    input.value = defaults[key];
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
            }
        });
    }

    // --- Image Preview ---
    document.querySelectorAll('input[data-preview-target]').forEach(input => {
        const previewImage = document.getElementById(input.dataset.previewTarget);
        if (!previewImage) return;
        const updatePreview = (src) => {
            previewImage.src = src || 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs='; // Use transparent pixel for empty src
        };
        if (input.type === 'file') {
            input.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = (re) => updatePreview(re.target.result);
                    reader.readAsDataURL(file);
                } else {
                    const urlInput = input.closest('.image-group').querySelector('input[type="text"]');
                    updatePreview(urlInput.value);
                }
            });
        } else {
            input.addEventListener('input', (e) => updatePreview(e.target.value));
        }
    });

    // --- Backup/Restore ---
    const restoreForm = document.getElementById('restore-form');
    if(restoreForm) {
        const fileInput = document.getElementById('backup_file');
        const previewEl = document.getElementById('json-preview');
        const restoreBtn = document.getElementById('restore-btn');
        fileInput.addEventListener('change', () => {
            const file = fileInput.files[0];
            if (file && file.type === 'application/json') {
                const reader = new FileReader();
                reader.onload = (e) => {
                    try {
                        previewEl.textContent = JSON.stringify(JSON.parse(e.target.result), null, 2);
                        previewEl.parentElement.style.display = 'block';
                        restoreBtn.disabled = false;
                    } catch (err) {
                        previewEl.textContent = 'خطا: فایل JSON معتبر نیست.';
                        restoreBtn.disabled = true;
                    }
                };
                reader.readAsText(file);
            } else {
                previewEl.parentElement.style.display = 'none';
                restoreBtn.disabled = true;
                if(file) showToast('لطفا فقط فایل .json انتخاب کنید.', 'error');
            }
        });
    }
});
