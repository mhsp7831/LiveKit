document.addEventListener("DOMContentLoaded", function () {
    // --- FIX: Add this URL validation helper function ---
    const isValidUrl = (string) => {
        // An empty string is valid because some link fields are optional.
        string = string ? string.trim() : "";
        if (!string) return true;
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    };
    const isValidImageUrl = (string) => {
        string = string ? string.trim() : "";
        if (!string) return true; // Optional fields are valid if empty

        let isStructurallyValid = false;

        // First, check if the URL structure is valid.
        if (string.startsWith("http://") || string.startsWith("https://")) {
            // For absolute URLs, use the full validator.
            isStructurallyValid = isValidUrl(string);
        } else {
            // For relative URLs (like our uploads), we assume the structure is valid.
            isStructurallyValid = true;
        }

        if (!isStructurallyValid) {
            return false;
        }

        // If the structure is valid, now check for a valid image extension.
        return /\.(jpg|jpeg|png|gif|svg|webp)$/i.test(string);
    };

    const validateUsername = (username) => {
        if (username.length < 3 || username.length > 20) {
            return {
                valid: false,
                message: "نام کاربری باید بین ۳ تا ۲۰ کاراکتر باشد.",
            };
        }
        if (!/^[a-zA-Z0-9_-]+$/.test(username)) {
            return {
                valid: false,
                message:
                    "نام کاربری فقط می‌تواند شامل حروف انگلیسی، اعداد، خط تیره و آندرلاین باشد.",
            };
        }
        return { valid: true };
    };

    const switchToTab = (tabId) => {
        // Remove 'active' class from the currently active tab and button
        document
            .querySelector(".sidebar-nav .active")
            ?.classList.remove("active");
        document.querySelector(".tab-panel.active")?.classList.remove("active");

        // Add 'active' class to the new tab and button
        const newTabButton = document.querySelector(
            `.sidebar-nav .tab-button[data-tab="${tabId}"]`
        );
        const newTabPanel = document.getElementById(tabId);

        if (newTabButton) newTabButton.classList.add("active");
        if (newTabPanel) newTabPanel.classList.add("active");

        // Show or hide the main "Save" button based on the tab
        const saveButtonContainer = document.querySelector(
            ".save-button-container"
        );
        if (["users", "backup"].includes(tabId)) {
            saveButtonContainer.style.display = "none";
        } else {
            saveButtonContainer.style.display = "";
        }
    };

    let isDirty = false; // FIX 7: Flag for unsaved changes

    // --- Global Elements ---
    const mainSaveBtn = document.getElementById("main-save-btn");
    const saveButtonContainer = document.querySelector(
        ".save-button-container"
    );

    // --- Toast Notification Handler ---
    const showToast = (message, type = "success") => {
        const container = document.getElementById("toast-container");
        if (!container) return;
        const toast = document.createElement("div");
        toast.className = `toast ${type}`;
        const icon =
            type === "success"
                ? '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>'
                : '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
        const tempEl = document.createElement("div");
        tempEl.innerHTML = message;
        toast.innerHTML = `${icon}<span>${tempEl.innerText}</span>`;
        container.appendChild(toast);
        setTimeout(() => toast.classList.add("show"), 100);
        setTimeout(() => {
            toast.classList.remove("show");
            toast.addEventListener("transitionend", () => toast.remove());
        }, 4000);
    };

    // --- Generic AJAX Requester ---
    const sendRequest = async (formData) => {
        if (!formData.has("csrf_token")) {
            const token = document.querySelector(
                'input[name="csrf_token"]'
            )?.value;
            if (token) formData.append("csrf_token", token);
        }
        const response = await fetch("ajax-handler.php", {
            method: "POST",
            body: formData,
            headers: { Accept: "application/json" },
        });
        if (!response.ok)
            throw new Error(`Server responded with status: ${response.status}`);
        const result = await response.json();
        if (!result.success)
            throw new Error(
                result.message || "An unknown server error occurred."
            );
        return result;
    };

    // --- Modal Logic ---
    const setupModal = (modalEl) => {
        if (!modalEl) return { show: () => {}, hide: () => {} };
        const hide = () => modalEl.classList.remove("active");
        const show = () => modalEl.classList.add("active");
        modalEl.addEventListener("click", (e) => {
            if (e.target === modalEl) hide();
        });
        modalEl
            .querySelectorAll(".modal-cancel-btn")
            .forEach((btn) => btn.addEventListener("click", hide));
        return { show, hide };
    };

    const confirmModal = setupModal(document.getElementById("confirm-modal"));
    const promptModal = setupModal(document.getElementById("prompt-modal"));
    const editUserModal = setupModal(
        document.getElementById("edit-user-modal")
    );

    const showConfirm = (title, message) => {
        return new Promise((resolve) => {
            const modalEl = document.getElementById("confirm-modal");
            modalEl.querySelector("#modal-title").textContent = title;
            modalEl.querySelector("#modal-text").textContent = message;
            const confirmBtn = modalEl.querySelector("#modal-confirm-btn");
            const newConfirmBtn = confirmBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

            newConfirmBtn.addEventListener(
                "click",
                () => {
                    confirmModal.hide();
                    resolve(true);
                },
                { once: true }
            );
            modalEl.querySelector("#modal-cancel-btn").onclick = () => {
                confirmModal.hide();
                resolve(false);
            };

            confirmModal.show();
        });
    };

    const showPrompt = (config) => {
        return new Promise((resolve) => {
            const modalEl = document.getElementById("prompt-modal");
            modalEl.querySelector("#prompt-title").textContent = config.title;
            modalEl.querySelector("#prompt-text").textContent = config.text;
            const input = modalEl.querySelector("#prompt-input");
            input.value = config.value || "";
            input.pattern = config.pattern || ".*";
            input.title = config.patternHint || "";

            const confirmBtn = modalEl.querySelector("#prompt-confirm-btn");
            const newConfirmBtn = confirmBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

            newConfirmBtn.addEventListener("click", () => {
                if (!input.checkValidity()) {
                    input.reportValidity();
                    return;
                }
                promptModal.hide();
                resolve(input.value.trim());
            });
            modalEl.querySelector("#prompt-cancel-btn").onclick = () => {
                promptModal.hide();
                resolve(null);
            };

            promptModal.show();
        });
    };

    // --- Form Dirty State & Unload Warning ---
    const setDirty = () => {
        if (!isDirty) isDirty = true;
        mainSaveBtn.disabled = false;
    };
    const setClean = () => {
        isDirty = false;
        mainSaveBtn.disabled = true;
    };
    window.addEventListener("beforeunload", (e) => {
        if (isDirty) {
            e.preventDefault();
            e.returnValue = "";
        }
    });

    // --- Image Preview Logic ---
    const setupImagePreview = (urlInput, fileInput, previewImage) => {
        const updatePreview = (src) => {
            previewImage.src =
                src ||
                "data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=";
            previewImage.style.display = src ? "" : "none";
        };
        fileInput.addEventListener("change", (e) => {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (re) => updatePreview(re.target.result);
                reader.readAsDataURL(file);
            } else {
                updatePreview(urlInput.value);
            }
        });
        urlInput.addEventListener("input", () => updatePreview(urlInput.value));
        updatePreview(urlInput.value);
    };

    // --- Dynamic Sortable List Manager ---
    const setupSortableList = (containerId, addButtonId, templateId) => {
        const container = document.getElementById(containerId);
        const addButton = document.getElementById(addButtonId);
        const template = document.getElementById(templateId);
        if (!container || !template) return;

        const setupItem = (item) => {
            item.querySelectorAll(".image-group").forEach((group) => {
                setupImagePreview(
                    group.querySelector(".preview-url-input"),
                    group.querySelector(".preview-file-input"),
                    group.querySelector(".image-preview")
                );
            });
            updateItemCounters(containerId);
        };

        container.querySelectorAll(".sortable-item").forEach(setupItem);

        if (addButton) {
            addButton.addEventListener("click", () => {
                const newItem =
                    template.content.cloneNode(true).firstElementChild;
                container.appendChild(newItem);
                setupItem(newItem);
                setDirty();
            });
        }

        new Sortable(container, {
            animation: 150,
            ghostClass: "sortable-ghost",
            handle: ".drag-handle",
            forceFallback: true,
            onEnd: () => {
                updateItemCounters(containerId);
                setDirty();
            },
        });
        updateItemCounters(containerId);
    };

    const updateItemCounters = (containerId) => {
        const container = document.getElementById(containerId);
        container.querySelectorAll(".sortable-item").forEach((item, index) => {
            const counter = item.querySelector(".item-counter");
            if (counter) counter.textContent = index + 1;
        });
    };

    // --- MAIN FORM SUBMISSION ---
    const settingsForm = document.getElementById("settings-form");
    settingsForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        const liveStartValue = document.getElementById("liveStart").value;
        const liveEndValue = document.getElementById("liveEnd").value;

        // Only validate if both fields are filled out
        if (liveStartValue && liveEndValue) {
            if (new Date(liveStartValue) >= new Date(liveEndValue)) {
                showToast("زمان شروع باید قبل از زمان پایان باشد.", "error");
                return; // Stop the submission
            }
        }

        // --- FIX: START of new URL validation logic ---
        try {
            let invalidUrl = null;
            let invalidInput = null;

            // Find the first invalid URL across all relevant fields
            document
                .querySelectorAll(
                    'input[name="homePage"], input[name="bannerLink"], [name="button_link[]"], [name="social_link[]"], [name="subtitle_link[]"]'
                )
                .forEach((input) => {
                    if (!isValidUrl(input.value)) {
                        invalidUrl = input.value;
                        invalidInput = input;
                    }
                });

            if (invalidUrl !== null) {
                showToast(`لینک وارد شده نامعتبر است: ${invalidUrl}`, "error");
                invalidInput.focus(); // Focus on the invalid field
                return; // Stop the submission
            }
        } catch (err) {
            // This handles cases where an input might not exist on the page
            console.error("Error during URL validation:", err);
        }
        // --- FIX: END of new URL validation logic ---

        try {
            let invalidImageUrl = null;
            let invalidImageInput = null;

            // Select all text inputs for image URLs
            document
                .querySelectorAll('input[name$="_url"]')
                .forEach((input) => {
                    if (!isValidImageUrl(input.value)) {
                        invalidImageUrl = input.value;
                        invalidImageInput = input;
                    }
                });

            if (invalidImageUrl !== null) {
                showToast(
                    `آدرس تصویر نامعتبر است: ${invalidImageUrl}`,
                    "error"
                );
                invalidImageInput.focus();
                return; // Stop submission
            }
        } catch (err) {
            console.error("Error during image URL validation:", err);
        }

        mainSaveBtn.disabled = true;
        mainSaveBtn.classList.add("loading");

        try {
            const formData = new FormData(settingsForm);
            const result = await sendRequest(formData);

            mainSaveBtn.classList.remove("loading");
            mainSaveBtn.classList.add("success");
            showToast(result.message);
            setClean();

            // --- FIX: START of new logic to update UI without reloading ---
            if (result.updated_data && result.updated_data.configs) {
                const newConfigs = result.updated_data.configs;

                // Helper function to strip "config/" prefix, same as PHP
                const getDashboardUrl = (path) => {
                    if (!path) return "";
                    return path.startsWith("config/")
                        ? path.substring("config/".length)
                        : path;
                };

                // Update main image fields
                ["logo", "preBanner", "endBanner", "banner"].forEach((key) => {
                    const group = document
                        .querySelector(`input[name="${key}_file"]`)
                        .closest(".image-group");
                    if (group) {
                        group.querySelector(".preview-url-input").value =
                            getDashboardUrl(newConfigs[key]);
                        group.querySelector(".image-preview").src =
                            getDashboardUrl(newConfigs[key]);
                        group.querySelector(".preview-file-input").value = ""; // Clear file input
                        group.querySelector(`input[name="${key}_old"]`).value =
                            newConfigs[key];
                    }
                });

                // Update social icon fields
                const socialItems = document.querySelectorAll(
                    "#socials-container .sortable-item"
                );
                if (
                    newConfigs.socials &&
                    socialItems.length === newConfigs.socials.length
                ) {
                    socialItems.forEach((item, index) => {
                        const newSocial = newConfigs.socials[index];
                        item.querySelector(".preview-url-input").value =
                            getDashboardUrl(newSocial.icon);
                        item.querySelector(".image-preview").src =
                            getDashboardUrl(newSocial.icon);
                        item.querySelector(".preview-file-input").value = ""; // Clear file input
                        item.querySelector(
                            'input[name="social_icon_old[]"]'
                        ).value = newSocial.icon;
                    });
                }
                const fetchIntervalInput =
                    document.getElementById("fetchInterval");
                if (fetchIntervalInput && newConfigs.fetchInterval) {
                    fetchIntervalInput.value = newConfigs.fetchInterval;
                }

                const subtitleDelayInput =
                    document.getElementById("subtitleDelay");
                if (subtitleDelayInput && newConfigs.subtitleDelay) {
                    subtitleDelayInput.value = newConfigs.subtitleDelay;
                }
            }
            // --- FIX: END of new logic ---

            setTimeout(() => {
                mainSaveBtn.classList.remove("success");
                // The reload is no longer needed
                // window.location.reload();
            }, 1500);
        } catch (error) {
            showToast(error.message, "error");
            mainSaveBtn.disabled = false;
            mainSaveBtn.classList.remove("loading");
        }
    });

    // --- EVENT DELEGATION FOR ALL OTHER ACTIONS ---
    document.body.addEventListener("click", async (e) => {
        const button = e.target.closest("button");
        if (!button) return;

        // Dynamic item removal
        if (button.classList.contains("remove-btn")) {
            const item = button.closest(".sortable-item");
            const listContainer = item
                ? item.closest(".sortable-list, .sortable-list-grid")
                : null;

            if (
                item &&
                listContainer &&
                (await showConfirm(
                    "تایید حذف",
                    "آیا از حذف این آیتم مطمئن هستید؟"
                ))
            ) {
                item.remove();
                setDirty();
                // Re-calculate counters
                updateItemCounters(listContainer.id);
            }
        }
        if (button.id === "remove-all-subtitles-btn") {
            if (
                await showConfirm(
                    "تایید حذف همه",
                    "آیا از حذف تمام زیرنویس‌ها مطمئن هستید؟"
                )
            ) {
                document.getElementById("subtitles-container").innerHTML = "";
                setDirty();
            }
        }

        // Color reset
        if (button.id === "reset-colors-btn") {
            const defaults = JSON.parse(button.dataset.defaults);
            const colorForm = button.closest(".card");
            for (const key in defaults) {
                const inputName =
                    key.replace(/_/g, "-") === "title"
                        ? "title-color"
                        : key.replace(/_/g, "-");
                const input = colorForm.querySelector(
                    `input[name="${inputName}"]`
                );
                if (input) {
                    input.value = defaults[key];
                    input.dispatchEvent(new Event("input", { bubbles: true })); // Trigger preview update
                }
            }
            setDirty();
        }

        // Header buttons
        const eventSelector = document.getElementById("event-selector");
        const currentEventId = eventSelector.value;
        const currentEventName =
            eventSelector.options[eventSelector.selectedIndex].text;

        switch (button.id) {
            case "create-event-btn": {
                const name = await showPrompt({
                    title: "ساخت رویداد جدید",
                    text: "یک نام برای رویداد جدید خود وارد کنید:",
                });
                if (name === null) return;
                const formData = new FormData();
                formData.append("action", "create_event");
                formData.append("event_name", name);
                try {
                    await sendRequest(formData);
                    showToast("رویداد جدید با موفقیت ساخته شد.");
                    window.location.reload();
                } catch (error) {
                    showToast(error.message, "error");
                }
                break;
            }
            case "rename-event-btn": {
                const newName = await showPrompt({
                    title: "تغییر نام رویداد",
                    text: `نام جدیدی برای "${currentEventName}" وارد کنید:`,
                    value: currentEventName,
                });
                if (newName === null || newName === currentEventName) return;
                const formData = new FormData();
                formData.append("action", "rename_event");
                formData.append("event_id", currentEventId);
                formData.append("event_name", newName);
                try {
                    await sendRequest(formData);
                    showToast("نام رویداد با موفقیت تغییر کرد.");
                    eventSelector.options[eventSelector.selectedIndex].text =
                        newName;
                } catch (error) {
                    showToast(error.message, "error");
                }
                break;
            }
            case "delete-event-btn": {
                if (
                    await showConfirm(
                        "تایید حذف رویداد",
                        `آیا از حذف رویداد "${currentEventName}" مطمئن هستید؟ این عمل غیرقابل بازگشت است.`
                    )
                ) {
                    const formData = new FormData();
                    formData.append("action", "delete_event");
                    formData.append("event_id", currentEventId);
                    try {
                        await sendRequest(formData);
                        showToast("رویداد با موفقیت حذف شد.");
                        window.location.reload();
                    } catch (error) {
                        showToast(error.message, "error");
                    }
                }
                break;
            }
            case "copy-event-id-btn":
                navigator.clipboard
                    .writeText(
                        document.querySelector(".event-id-display code")
                            .textContent
                    )
                    .then(() => showToast("شناسه رویداد کپی شد!"))
                    .catch(() => showToast("خطا در کپی کردن شناسه.", "error"));
                break;
            case "edit-event-id-btn": {
                const newId = await showPrompt({
                    title: "ویرایش شناسه رویداد",
                    text: "شناسه جدید باید منحصر به فرد باشد و فقط شامل حروف انگلیسی، اعداد و آندرلاین (_) باشد.",
                    value: currentEventId,
                    pattern: "^[a-zA-Z0-9_]+$",
                    patternHint:
                        "فقط حروف انگلیسی، اعداد و آندرلاین (_) مجاز است.",
                });
                if (newId === null || newId === currentEventId) return;
                const formData = new FormData();
                formData.append("action", "edit_event_id");
                formData.append("current_event_id", currentEventId);
                formData.append("new_event_id", newId);
                try {
                    await sendRequest(formData);
                    showToast(
                        "شناسه با موفقیت تغییر کرد. صفحه بارگذاری مجدد می‌شود."
                    );
                    window.location.reload();
                } catch (error) {
                    showToast(error.message, "error");
                }
                break;
            }
        }

        // User management buttons
        if (button.closest(".user-item")) {
            const userId = button.dataset.id;
            const username = button.dataset.username;
            if (button.classList.contains("edit-user-btn")) {
                document.getElementById("edit_user_id").value = userId;
                document.getElementById("edit_username").value = username;
                document.getElementById("edit_password").value = "";
                editUserModal.show();
            } else if (button.classList.contains("delete-user-btn")) {
                const message = button.hasAttribute("data-self-delete")
                    ? `آیا از حذف حساب کاربری خودتان ("${username}") مطمئن هستید؟`
                    : `آیا از حذف کاربر "${username}" مطمئن هستید؟`;
                if (await showConfirm("تایید حذف کاربر", message)) {
                    const formData = new FormData();
                    formData.append("action", "delete_user");
                    formData.append("user_id", userId);
                    try {
                        const result = await sendRequest(formData);
                        showToast(result.message);
                        if (result.self_delete) {
                            setTimeout(
                                () => (window.location.href = "logout.php"),
                                1500
                            );
                        } else {
                            setTimeout(() => window.location.reload(), 1500);
                        }
                    } catch (error) {
                        showToast(error.message, "error");
                    }
                }
            }
        }
    });

    // Standalone forms (user management, restore)
    document.querySelectorAll(".standalone-form").forEach((form) => {
        form.addEventListener("submit", async (e) => {
            e.preventDefault();
            const usernameInput = form.querySelector('input[name="username"]');
            if (usernameInput) {
                const validationResult = validateUsername(usernameInput.value);
                if (!validationResult.valid) {
                    showToast(validationResult.message, "error");
                    return; // Stop the submission
                }
            }
            const submitButton = form.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.classList.add("loading");
            try {
                const result = await sendRequest(new FormData(form));
                showToast(result.message);
                setTimeout(() => window.location.reload(), 1500);
            } catch (error) {
                showToast(error.message, "error");
                submitButton.disabled = false;
                submitButton.classList.remove("loading");
            }
        });
    });

    // Event Selector Change
    document
        .getElementById("event-selector")
        .addEventListener("change", (e) => {
            if (
                isDirty &&
                !confirm(
                    "تغییرات ذخیره نشده از بین خواهند رفت. آیا مطمئن هستید؟"
                )
            ) {
                e.target.value =
                    e.target.querySelector("option[selected]").value; // Revert selection
                return;
            }
            const formData = new FormData();
            formData.append("action", "switch_event");
            formData.append("event_id", e.target.value);
            sendRequest(formData)
                .then(() => window.location.reload())
                .catch((err) => showToast(err.message, "error"));
        });

    // Tab Switching & Save Button Visibility
    document.querySelectorAll(".sidebar-nav .tab-button").forEach((button) => {
        button.addEventListener("click", (e) => {
            e.preventDefault();
            const tabId = button.dataset.tab;

            // Save the clicked tab's ID to localStorage
            localStorage.setItem("activeDashboardTab", tabId);

            // Use our new function to switch to the tab
            switchToTab(tabId);
        });
    });

    // Backup Restore Preview
    const backupFileInput = document.getElementById("backup_file");
    if (backupFileInput) {
        backupFileInput.addEventListener("change", () => {
            const previewEl = document.getElementById("json-preview");
            const restoreBtn = document.getElementById("restore-btn");
            const file = backupFileInput.files[0];
            if (file && file.type === "application/json") {
                const reader = new FileReader();
                reader.onload = (e) => {
                    try {
                        previewEl.textContent = JSON.stringify(
                            JSON.parse(e.target.result),
                            null,
                            2
                        );
                        previewEl.parentElement.style.display = "block";
                        restoreBtn.disabled = false;
                    } catch (err) {
                        previewEl.textContent = "خطا: فایل JSON معتبر نیست.";
                        restoreBtn.disabled = true;
                    }
                };
                reader.readAsText(file);
            } else {
                previewEl.parentElement.style.display = "none";
                restoreBtn.disabled = true;
                if (file)
                    showToast("لطفا فقط فایل .json انتخاب کنید.", "error");
            }
        });
    }

    // Color preview update
    document.querySelector("#appearance").addEventListener("input", (e) => {
        if (e.target.type === "color") {
            let varName = e.target.name.replace("-color", "");
            document
                .querySelector(".color-preview-area")
                ?.style.setProperty("--" + varName, e.target.value);
        }
    });

    // Mark form as dirty on any input
    settingsForm.addEventListener("input", setDirty);

    // Initial call to set up the dashboard state
    const initializeDashboard = () => {
        setupSortableList(
            "buttons-container",
            "add-button-btn",
            "button-template"
        );
        setupSortableList(
            "socials-container",
            "add-social-btn",
            "social-template"
        );
        setupSortableList(
            "subtitles-container",
            "add-subtitle-btn",
            "subtitle-template"
        );

        document.querySelectorAll(".image-group").forEach((group) => {
            setupImagePreview(
                group.querySelector(".preview-url-input"),
                group.querySelector(".preview-file-input"),
                group.querySelector(".image-preview")
            );
        });

        // Restore the last active tab from localStorage
        const savedTabId = localStorage.getItem("activeDashboardTab");
        if (savedTabId && document.getElementById(savedTabId)) {
            switchToTab(savedTabId);
        } else {
            // If no tab is saved, ensure the default tab is correctly set up
            switchToTab("settings");
        }
    };

    initializeDashboard();
});
