/**
 * VGT OMEGA VAULT: Decoupled Single-Page Navigation, Dynamic SVG Analytics & Drag-and-Drop Form Builder
 */

document.addEventListener('DOMContentLoaded', () => {
    
    // 1. Initial State Router Setup
    const navButtons = document.querySelectorAll('.vgt-nav-btn');
    const sections = document.querySelectorAll('.vgt-section');

    const switchTab = (targetId) => {
        navButtons.forEach(btn => {
            if (btn.getAttribute('data-target') === targetId) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        sections.forEach(sec => {
            if (sec.id === targetId) {
                sec.classList.add('active');
            } else {
                sec.classList.remove('active');
            }
        });

        // Initialize dynamic charts if returning to dashboard
        if (targetId === 'vgt-sec-dashboard') {
            renderSvgAnalytics();
        }
    };

    navButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const target = btn.getAttribute('data-target');
            switchTab(target);
        });
    });

    // 2. High-Performance Zero-Dependency SVG Graph Generator
    const renderSvgAnalytics = () => {
        const chartElement = document.getElementById('vgt-analytics-chart');
        if (!chartElement) return;

        const data = window.vgtAdminParams?.timeline || [];
        if (data.length === 0) {
            chartElement.innerHTML = `<text x="50%" y="50%" text-anchor="middle" class="vgt-chart-text">No cryptographic transaction events registered in timeframe.</text>`;
            return;
        }

        const width = chartElement.clientWidth || 1100;
        const height = 180;
        const paddingLeft = 40;
        const paddingRight = 40;
        const paddingTop = 20;
        const paddingBottom = 30;

        const maxVal = Math.max(...data.map(d => d.count), 5);
        const stepX = (width - paddingLeft - paddingRight) / Math.max(data.length - 1, 1);
        
        let points = [];
        let gridlines = '';
        let xLabels = '';

        for (let i = 0; i <= 4; i++) {
            const yVal = Math.round((maxVal / 4) * i);
            const yPos = height - paddingBottom - ((height - paddingTop - paddingBottom) / 4) * i;
            gridlines += `<line x1="${paddingLeft}" y1="${yPos}" x2="${width - paddingRight}" y2="${yPos}" class="vgt-chart-gridline" />`;
            gridlines += `<text x="${paddingLeft - 10}" y="${yPos + 4}" text-anchor="end" class="vgt-chart-text">${yVal}</text>`;
        }

        data.forEach((item, index) => {
            const x = paddingLeft + index * stepX;
            const y = height - paddingBottom - ((item.count / maxVal) * (height - paddingTop - paddingBottom));
            points.push({ x, y });

            const displayDate = item.date.substring(5); 
            if (data.length < 8 || index % 2 === 0) {
                xLabels += `<text x="${x}" y="${height - 10}" text-anchor="middle" class="vgt-chart-text">${displayDate}</text>`;
            }
        });

        const linePath = points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ');
        const areaPath = `${linePath} L ${points[points.length - 1].x} ${height - paddingBottom} L ${points[0].x} ${height - paddingBottom} Z`;

        chartElement.innerHTML = `
            <defs>
                <linearGradient id="chart-gradient" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#d4af37" stop-opacity="0.3"/>
                    <stop offset="100%" stop-color="#d4af37" stop-opacity="0"/>
                </linearGradient>
            </defs>
            ${gridlines}
            <path d="${areaPath}" class="vgt-chart-area" />
            <path d="${linePath}" class="vgt-chart-line" />
            ${points.map(p => `<circle cx="${p.x}" cy="${p.y}" r="4" fill="#d4af37" />`).join('')}
            ${xLabels}
        `;
    };

    renderSvgAnalytics();
    window.addEventListener('resize', renderSvgAnalytics);

    // 3. Toast Notifications Alert
    const showToast = (message, isSuccess = true) => {
        let toast = document.getElementById('vgt-toast-alert');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'vgt-toast-alert';
            toast.className = 'vgt-toast';
            document.body.appendChild(toast);
        }

        toast.innerText = message;
        toast.className = `vgt-toast active ${isSuccess ? 'success' : 'error'}`;

        setTimeout(() => {
            toast.classList.remove('active');
        }, 4000);
    };

    // 4. Clipboard Shortcode Copy Helper
    document.querySelectorAll('.vgt-shortcode-copy-wrapper').forEach(wrapper => {
        wrapper.addEventListener('click', () => {
            const code = wrapper.getAttribute('data-shortcode');
            navigator.clipboard.writeText(code).then(() => {
                showToast('Shortcode in die Zwischenablage kopiert!', true);
            }).catch(() => {
                showToast('Fehler beim Kopieren.', false);
            });
        });
    });

    // 5. Config settings page AJAX Save
    const configForm = document.getElementById('vgt-config-form');
    if (configForm) {
        configForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = configForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerText;
            submitBtn.innerText = 'SAVING CONFIGURATION...';
            submitBtn.disabled = true;

            const formData = new FormData(configForm);
            formData.append('action', 'vgt_save_config');
            formData.append('security', window.vgtAdminParams?.saveNonce);

            try {
                const response = await fetch(window.vgtAdminParams.ajaxUrl, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.data.message || 'Configuration saved.', true);
                } else {
                    showToast(result.data.message || 'Saving failed.', false);
                }
            } catch (error) {
                showToast('Kernel communication failure.', false);
            } finally {
                submitBtn.innerText = originalText;
                submitBtn.disabled = false;
            }
        });
    }

    /* ==============================================================================
     * FORMS & FUNNELS D Drag-and-Drop STATE ENGINE
     * ============================================================================== */
    let currentFormState = {
        id: 0,
        title: '',
        type: 'form',
        fields: [],
        settings: {
            theme: 'dark',
            gold_accent: '#d4af37',
            background_color: '#030303',
            background_image: '',
            text_color: '#f9fafb',
            button_text: 'Initialize Encryption',
            border_radius: '8px',
            padding: '3rem',
            width: '780px',
            subtitle: 'End-to-End Encrypted Tunnel'
        }
    };
    let selectedFieldId = null;

    // Drag-and-Drop Setup
    const dragModules = document.querySelectorAll('.vgt-drag-module');
    dragModules.forEach(mod => {
        mod.addEventListener('dragstart', (e) => {
            e.dataTransfer.setData('text/plain', mod.getAttribute('data-type'));
        });
    });

    const canvas = document.getElementById('vgt-canvas');
    if (canvas) {
        canvas.addEventListener('dragover', (e) => {
            e.preventDefault();
        });

        canvas.addEventListener('drop', (e) => {
            e.preventDefault();
            const type = e.dataTransfer.getData('text/plain');
            if (!type) return;

            // Add new field to state
            const fieldId = 'vgt_field_' + Date.now();
            const newField = {
                id: fieldId,
                type: type,
                label: type.charAt(0).toUpperCase() + type.slice(1) + ' Field',
                placeholder: 'Geben Sie Daten ein...',
                required: false,
                options: 'Option 1, Option 2, Option 3',
                media_url: ''
            };

            if (type === 'step_break') {
                newField.label = 'Trichter-Schritt (Separator)';
            } else if (type === 'heading') {
                newField.label = 'Überschrift';
            } else if (type === 'paragraph') {
                newField.label = 'Hier steht Ihr Text...';
            } else if (type === 'image' || type === 'video') {
                newField.label = type.charAt(0).toUpperCase() + type.slice(1) + ' URL';
            }

            currentFormState.fields.push(newField);
            renderCanvas();
            selectField(fieldId);
        });
    }

    // Sidebar Properties Tab Navigation
    const propTabs = document.querySelectorAll('.vgt-sidebar-tab-btn');
    propTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            propTabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            
            const target = tab.getAttribute('data-tab');
            document.querySelectorAll('.vgt-sidebar-tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById('vgt-sidebar-tab-' + target).classList.add('active');
        });
    });

    // Property Inputs Binder
    const propLabel = document.getElementById('prop-label');
    const propPlaceholder = document.getElementById('prop-placeholder');
    const propRequired = document.getElementById('prop-required');
    const propOptions = document.getElementById('prop-options');
    const propMediaUrl = document.getElementById('prop-media-url');
    const propTextColor = document.getElementById('prop-text-color');

    const updateSelectedFieldInState = () => {
        if (!selectedFieldId) return;

        if (selectedFieldId === 'form_title') {
            const val = propLabel.value;
            currentFormState.title = val;
            const titleInput = document.getElementById('vgt-builder-title');
            if (titleInput) titleInput.value = val;
            
            if (!currentFormState.settings) currentFormState.settings = {};
            currentFormState.settings.title_color = propTextColor.value;
            
            applyLiveStyles();
            return;
        }

        if (selectedFieldId === 'form_subtitle') {
            if (!currentFormState.settings) currentFormState.settings = {};
            currentFormState.settings.subtitle = propLabel.value;
            currentFormState.settings.subtitle_color = propTextColor.value;
            
            applyLiveStyles();
            return;
        }

        const field = currentFormState.fields.find(f => f.id === selectedFieldId);
        if (!field) return;

        field.label = propLabel.value;
        field.placeholder = propPlaceholder.value;
        field.required = propRequired.checked;
        field.options = propOptions.value;
        field.media_url = propMediaUrl.value;
        field.text_color = propTextColor.value;

        // Sync to canvas live preview
        renderCanvas();
    };

    [propLabel, propPlaceholder, propOptions, propMediaUrl, propTextColor].forEach(el => {
        if (el) {
            el.addEventListener('input', updateSelectedFieldInState);
            el.addEventListener('change', updateSelectedFieldInState);
        }
    });
    if (propRequired) {
        propRequired.addEventListener('change', updateSelectedFieldInState);
    }

    // Design Inputs Binder
    const designTheme = document.getElementById('design-theme');
    const designGoldAccent = document.getElementById('design-gold-accent');
    const designBgColor = document.getElementById('design-bg-color');
    const designTextColor = document.getElementById('design-text-color');
    const designBgImage = document.getElementById('design-bg-image');
    const designBtnText = document.getElementById('design-btn-text');
    const designBorderRadius = document.getElementById('design-border-radius');
    const designPadding = document.getElementById('design-padding');
    const designWidth = document.getElementById('design-width');

    const updateDesignSettingsInState = () => {
        if (!currentFormState.settings) currentFormState.settings = {};
        currentFormState.settings.theme = designTheme.value;
        currentFormState.settings.gold_accent = designGoldAccent.value;
        currentFormState.settings.background_color = designBgColor.value;
        currentFormState.settings.text_color = designTextColor.value;
        currentFormState.settings.background_image = designBgImage.value;
        currentFormState.settings.button_text = designBtnText.value;
        currentFormState.settings.border_radius = designBorderRadius.value;
        currentFormState.settings.padding = designPadding.value;
        currentFormState.settings.width = designWidth.value;
    };

    const applyLiveStyles = () => {
        const wrapper = document.getElementById('vgt-builder-preview-wrapper');
        if (!wrapper) return;

        const settings = currentFormState.settings || {};
        
        // CSS Custom Properties
        wrapper.style.setProperty('--vgt-radius', settings.border_radius || '8px');
        wrapper.style.setProperty('--vgt-padding', settings.padding || '3rem');
        wrapper.style.setProperty('--vgt-width', settings.width || '780px');
        wrapper.style.setProperty('--vgt-gold', settings.gold_accent || '#d4af37');
        wrapper.style.setProperty('--vgt-text', settings.text_color || '#f9fafb');

        // Apply styles directly as fallback / overrides
        wrapper.style.padding = settings.padding || '3rem';
        wrapper.style.borderRadius = settings.border_radius || '8px';
        wrapper.style.maxWidth = settings.width || '780px';
        wrapper.style.color = settings.text_color || '#f9fafb';

        // Background image or color
        if (settings.background_image) {
            wrapper.style.backgroundImage = `url('${settings.background_image}')`;
            wrapper.style.backgroundSize = 'cover';
            wrapper.style.backgroundPosition = 'center';
        } else {
            wrapper.style.backgroundImage = 'none';
            wrapper.style.backgroundColor = settings.background_color || '#030303';
        }

        // Theme classes
        wrapper.className = 'vgt-fe-wrapper'; // reset classes
        const theme = settings.theme || 'dark';
        wrapper.classList.add(`vgt-theme-${theme}`);

        // Subtitle
        const subEl = document.getElementById('vgt-preview-subtitle');
        if (subEl) {
            subEl.innerText = settings.subtitle || (currentFormState.type === 'funnel' ? 'Secure Step Tunnel' : 'End-to-End Encrypted Tunnel');
            subEl.style.color = settings.subtitle_color || '';
        }

        // Preview title
        const titleEl = document.getElementById('vgt-preview-title');
        if (titleEl) {
            titleEl.innerText = currentFormState.title || 'Form Title';
            titleEl.style.color = settings.title_color || '';
        }
    };

    [designTheme, designGoldAccent, designBgColor, designTextColor, designBgImage, designBtnText, designBorderRadius, designPadding, designWidth].forEach(el => {
        if (el) {
            el.addEventListener('input', () => {
                updateDesignSettingsInState();
                applyLiveStyles();
            });
            el.addEventListener('change', () => {
                updateDesignSettingsInState();
                applyLiveStyles();
            });
        }
    });

    // Inline edit listeners for preview header
    const previewTitle = document.getElementById('vgt-preview-title');
    const previewSubtitle = document.getElementById('vgt-preview-subtitle');
    
    if (previewTitle) {
        previewTitle.addEventListener('click', (e) => {
            e.stopPropagation();
            selectField('form_title');
        });
        previewTitle.addEventListener('blur', () => {
            const val = previewTitle.innerText.trim();
            const titleInput = document.getElementById('vgt-builder-title');
            if (titleInput) titleInput.value = val;
            currentFormState.title = val;
        });
        previewTitle.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                previewTitle.blur();
            }
        });
    }

    if (previewSubtitle) {
        previewSubtitle.addEventListener('click', (e) => {
            e.stopPropagation();
            selectField('form_subtitle');
        });
        previewSubtitle.addEventListener('blur', () => {
            const val = previewSubtitle.innerText.trim();
            if (!currentFormState.settings) currentFormState.settings = {};
            currentFormState.settings.subtitle = val;
        });
        previewSubtitle.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                previewSubtitle.blur();
            }
        });
    }

    const builderTitleInput = document.getElementById('vgt-builder-title');
    if (builderTitleInput) {
        builderTitleInput.addEventListener('input', () => {
            const val = builderTitleInput.value.trim();
            currentFormState.title = val;
            const pTitle = document.getElementById('vgt-preview-title');
            if (pTitle) {
                pTitle.innerText = val || 'Form Title';
            }
        });
    }

    const normalizeHexColor = (color) => {
        if (!color) return '#ffffff';
        color = color.trim();
        if (color.startsWith('#')) {
            if (color.length === 4) {
                return '#' + color[1] + color[1] + color[2] + color[2] + color[3] + color[3];
            }
            if (color.length === 7) {
                return color;
            }
        }
        try {
            const tempCanvas = document.createElement('canvas');
            tempCanvas.width = 1;
            tempCanvas.height = 1;
            const ctx = tempCanvas.getContext('2d');
            ctx.fillStyle = color;
            return ctx.fillStyle;
        } catch (e) {
            return '#ffffff';
        }
    };

    const selectField = (fieldId) => {
        selectedFieldId = fieldId;
        document.querySelectorAll('.vgt-canvas-field').forEach(el => el.classList.remove('selected'));
        const titleEl = document.getElementById('vgt-preview-title');
        const subtitleEl = document.getElementById('vgt-preview-subtitle');
        if (titleEl) titleEl.classList.remove('selected');
        if (subtitleEl) subtitleEl.classList.remove('selected');
        
        // Show panel details
        document.getElementById('vgt-no-field-selected').style.display = 'none';
        document.getElementById('vgt-field-properties-form').style.display = 'block';

        if (fieldId === 'form_title') {
            if (titleEl) titleEl.classList.add('selected');
            propLabel.value = currentFormState.title || '';
            propTextColor.value = normalizeHexColor(currentFormState.settings.title_color || currentFormState.settings.text_color || '#f9fafb');
            
            document.querySelector('.prop-placeholder-wrap').style.display = 'none';
            document.querySelector('.prop-required-wrap').style.display = 'none';
            document.querySelector('.prop-options-wrap').style.display = 'none';
            document.querySelector('.prop-media-url-wrap').style.display = 'none';
            document.querySelector('.prop-text-color-wrap').style.display = 'block';
            
            propTabs[0].click();
            return;
        }

        if (fieldId === 'form_subtitle') {
            if (subtitleEl) subtitleEl.classList.add('selected');
            propLabel.value = currentFormState.settings.subtitle || '';
            propTextColor.value = normalizeHexColor(currentFormState.settings.subtitle_color || currentFormState.settings.text_color || '#8e939e');
            
            document.querySelector('.prop-placeholder-wrap').style.display = 'none';
            document.querySelector('.prop-required-wrap').style.display = 'none';
            document.querySelector('.prop-options-wrap').style.display = 'none';
            document.querySelector('.prop-media-url-wrap').style.display = 'none';
            document.querySelector('.prop-text-color-wrap').style.display = 'block';
            
            propTabs[0].click();
            return;
        }

        const element = document.querySelector(`.vgt-canvas-field[data-id="${fieldId}"]`);
        if (element) element.classList.add('selected');

        const field = currentFormState.fields.find(f => f.id === fieldId);
        if (!field) return;

        // Populate fields properties panel
        propLabel.value = field.label;
        propPlaceholder.value = field.placeholder || '';
        propRequired.checked = !!field.required;
        propOptions.value = field.options || '';
        propMediaUrl.value = field.media_url || '';
        propTextColor.value = normalizeHexColor(field.text_color || currentFormState.settings.text_color || '#f9fafb');

        // Toggle panel inputs visibility depending on field type
        const type = field.type;
        document.querySelector('.prop-placeholder-wrap').style.display = (type === 'step_break' || type === 'image' || type === 'video' || type === 'heading' || type === 'paragraph') ? 'none' : 'block';
        document.querySelector('.prop-required-wrap').style.display = (type === 'step_break' || type === 'image' || type === 'video' || type === 'heading' || type === 'paragraph') ? 'none' : 'block';
        document.querySelector('.prop-options-wrap').style.display = (type === 'select' || type === 'radio') ? 'block' : 'none';
        document.querySelector('.prop-media-url-wrap').style.display = (type === 'image' || type === 'video') ? 'block' : 'none';
        document.querySelector('.prop-text-color-wrap').style.display = (type === 'step_break' || type === 'image' || type === 'video') ? 'none' : 'block';

        // Direct tab switch
        propTabs[0].click();
    };

    // Helper to escape HTML safely
    const escapeHtml = (str) => {
        if (!str) return '';
        return str
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    };

    // Canvas Rendering Loop
    const renderCanvas = () => {
        if (!canvas) return;
        canvas.innerHTML = '';

        const totalSteps = currentFormState.fields.filter(f => f.type === 'step_break').length + 1;
        let stepCount = 1;
        
        let stepWrap = document.createElement('div');
        stepWrap.className = 'vgt-canvas-step';
        stepWrap.innerHTML = `
            <div class="vgt-canvas-step-header">
                <span class="vgt-canvas-step-title">Schritt 1</span>
            </div>
            <div class="vgt-canvas-fields-container" data-step-idx="0"></div>
        `;
        canvas.appendChild(stepWrap);

        let fieldsContainer = stepWrap.querySelector('.vgt-canvas-fields-container');

        currentFormState.fields.forEach((field, index) => {
            if (field.type === 'step_break') {
                // Append step navigation buttons to the finished step container
                appendStepNavigation(stepWrap, stepCount - 1, totalSteps);
                
                stepCount++;
                stepWrap = document.createElement('div');
                stepWrap.className = 'vgt-canvas-step';
                stepWrap.innerHTML = `
                    <div class="vgt-canvas-step-header">
                        <span class="vgt-canvas-step-title">Schritt ${stepCount}</span>
                        <button type="button" class="vgt-canvas-step-delete" data-idx="${index}">[Schritt löschen]</button>
                    </div>
                    <div class="vgt-canvas-fields-container" data-step-idx="${stepCount-1}"></div>
                `;
                canvas.appendChild(stepWrap);
                fieldsContainer = stepWrap.querySelector('.vgt-canvas-fields-container');
                return;
            }

            // Build dynamic UI previews for inputs
            let fieldInner = '';
            const type = field.type;
            const placeholderVal = field.placeholder || 'Geben Sie Daten ein...';

            if (type === 'email') {
                fieldInner = `
                    <div class="vgt-input-wrapper">
                        <div class="vgt-input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                        </div>
                        <input type="email" class="vgt-fe-input" placeholder="${escapeHtml(placeholderVal)}" disabled>
                    </div>
                `;
            } else if (type === 'number') {
                fieldInner = `
                    <input type="number" class="vgt-fe-input" placeholder="${escapeHtml(placeholderVal)}" style="padding-left:1rem;" disabled>
                `;
            } else if (type === 'textarea') {
                fieldInner = `
                    <textarea class="vgt-fe-input vgt-fe-textarea" placeholder="${escapeHtml(placeholderVal)}" style="padding-left:1rem;" disabled></textarea>
                `;
            } else if (type === 'select') {
                const options = field.options ? field.options.split(',') : [];
                let optionsHTML = `<option value="">${escapeHtml(placeholderVal)}</option>`;
                options.forEach(opt => {
                    const optClean = opt.trim();
                    if (optClean) {
                        optionsHTML += `<option value="${escapeHtml(optClean)}">${escapeHtml(optClean)}</option>`;
                    }
                });
                fieldInner = `
                    <div class="vgt-input-wrapper">
                        <select class="vgt-fe-input" style="padding-left:1rem; appearance:none;" disabled>
                            ${optionsHTML}
                        </select>
                        <div class="vgt-select-arrow">▼</div>
                    </div>
                `;
            } else if (type === 'radio') {
                const options = field.options ? field.options.split(',') : ['Option A', 'Option B'];
                let optionsHTML = '';
                options.forEach(opt => {
                    const optClean = opt.trim();
                    optionsHTML += `
                        <label class="vgt-radio-label">
                            <input type="radio" disabled>
                            <span>${escapeHtml(optClean)}</span>
                        </label>
                    `;
                });
                fieldInner = `
                    <div class="vgt-radio-group">
                        ${optionsHTML}
                    </div>
                `;
            } else if (type === 'file') {
                fieldInner = `
                    <input type="file" class="vgt-fe-input vgt-file-upload" style="padding:0.8rem 1rem;" disabled>
                `;
            } else if (type === 'image') {
                const imgUrl = field.media_url || 'https://via.placeholder.com/400x150?text=No+Image+Selected';
                fieldInner = `
                    <img src="${escapeHtml(imgUrl)}" alt="${escapeHtml(field.label)}" class="vgt-media-img" style="max-width:100%; border-radius:8px; display:block;">
                `;
            } else if (type === 'video') {
                const vurl = field.media_url || '';
                if (vurl.includes('youtube.com') || vurl.includes('youtu.be')) {
                    let yid = '';
                    const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
                    const match = vurl.match(regExp);
                    if (match && match[2].length === 11) {
                        yid = match[2];
                    }
                    fieldInner = yid ? `
                        <div class="vgt-video-responsive">
                            <iframe src="https://www.youtube.com/embed/${yid}" frameborder="0" allowfullscreen></iframe>
                        </div>
                    ` : `
                        <div style="background:rgba(0,0,0,0.5); padding:1.5rem; text-align:center; border-radius:8px; border:1px dashed rgba(255,255,255,0.1); font-family:monospace; font-size:0.75rem; color:var(--vgt-text-muted);">[YouTube Video Embed Preview]</div>
                    `;
                } else {
                    fieldInner = `
                        <video src="${escapeHtml(vurl)}" controls class="vgt-media-video" style="width:100%; border-radius:8px; display:block;"></video>
                    `;
                }
            } else if (type === 'heading') {
                fieldInner = `
                    <h3 class="vgt-fe-heading" style="color: ${field.text_color || 'var(--vgt-text)'}; font-weight: 800; font-size: 1.55rem; margin: 0.5rem 0; font-family: inherit; text-align: left; border-bottom: 1px dashed rgba(255,255,255,0.1); outline: none;" contenteditable="true">${escapeHtml(field.label)}</h3>
                `;
            } else if (type === 'paragraph') {
                fieldInner = `
                    <p class="vgt-fe-paragraph" style="color: ${field.text_color || 'var(--vgt-text)'}; opacity: 0.85; font-size: 0.95rem; line-height: 1.6; margin: 0.5rem 0; font-family: inherit; text-align: left; border-bottom: 1px dashed rgba(255,255,255,0.1); outline: none;" contenteditable="true">${escapeHtml(field.label)}</p>
                `;
            } else {
                fieldInner = `
                    <div class="vgt-input-wrapper">
                        <div class="vgt-input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </div>
                        <input type="text" class="vgt-fe-input" placeholder="${escapeHtml(placeholderVal)}" disabled>
                    </div>
                `;
            }

            const fieldDiv = document.createElement('div');
            fieldDiv.className = 'vgt-canvas-field';
            if (field.id === selectedFieldId) fieldDiv.classList.add('selected');
            fieldDiv.setAttribute('data-id', field.id);
            
            const showLabel = (type !== 'image' && type !== 'video' && type !== 'heading' && type !== 'paragraph');
            const labelHtml = showLabel ? `
                <div class="vgt-canvas-label-wrapper" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem; width: 100%;">
                    <label class="vgt-fe-label" contenteditable="true" data-field-id="${field.id}" title="Click to edit label directly" style="cursor:text; display:inline-block; outline:none; border-bottom:1px dashed rgba(255,255,255,0.15); color: ${field.text_color || 'inherit'};">${escapeHtml(field.label)}</label>
                    ${field.required ? '<span style="color: var(--vgt-gold); font-size:0.8rem; margin-left: 2px;">*</span>' : ''}
                </div>
            ` : '';

            fieldDiv.innerHTML = `
                <div class="vgt-canvas-field-content" style="flex:1; width:100%;">
                    ${labelHtml}
                    ${fieldInner}
                </div>
                
                <div class="vgt-canvas-field-actions" style="margin-left:1.5rem; display:flex; gap:0.35rem; align-items:center;">
                    <span class="vgt-drag-handle" style="cursor:grab; font-size:1.1rem; color:var(--vgt-text-muted); padding: 0.25rem;">⠿</span>
                    <button type="button" class="vgt-btn-row-action move-up" data-idx="${index}" style="padding:4px 8px; font-size:11px;" title="Up">↑</button>
                    <button type="button" class="vgt-btn-row-action move-down" data-idx="${index}" style="padding:4px 8px; font-size:11px;" title="Down">↓</button>
                    <button type="button" class="vgt-canvas-field-delete" data-idx="${index}" style="padding:4px 8px; font-size:11px;" title="Remove">✕</button>
                </div>
            `;

            // Setup Inline Label Editing & Selection
            const labelEl = fieldDiv.querySelector('.vgt-fe-label, .vgt-fe-heading, .vgt-fe-paragraph');
            if (labelEl) {
                labelEl.addEventListener('click', (e) => {
                    e.stopPropagation();
                    selectField(field.id);
                });
                
                labelEl.addEventListener('blur', () => {
                    const newLabel = labelEl.innerText.trim();
                    field.label = newLabel;
                    if (selectedFieldId === field.id) {
                        propLabel.value = newLabel;
                    }
                });

                labelEl.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        labelEl.blur();
                    }
                });
            }

            fieldDiv.addEventListener('click', (e) => {
                if (e.target.closest('.vgt-canvas-field-actions') || e.target.closest('.vgt-fe-label, .vgt-fe-heading, .vgt-fe-paragraph')) return;
                selectField(field.id);
            });

            fieldsContainer.appendChild(fieldDiv);
        });

        // Append navigation to the last step
        appendStepNavigation(stepWrap, stepCount - 1, totalSteps);

        // Reorder & Action bindings
        canvas.querySelectorAll('.move-up').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const idx = parseInt(btn.getAttribute('data-idx'));
                if (idx > 0) {
                    const temp = currentFormState.fields[idx];
                    currentFormState.fields[idx] = currentFormState.fields[idx - 1];
                    currentFormState.fields[idx - 1] = temp;
                    renderCanvas();
                }
            });
        });

        canvas.querySelectorAll('.move-down').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const idx = parseInt(btn.getAttribute('data-idx'));
                if (idx < currentFormState.fields.length - 1) {
                    const temp = currentFormState.fields[idx];
                    currentFormState.fields[idx] = currentFormState.fields[idx + 1];
                    currentFormState.fields[idx + 1] = temp;
                    renderCanvas();
                }
            });
        });

        canvas.querySelectorAll('.vgt-canvas-field-delete, .vgt-canvas-step-delete').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const idx = parseInt(btn.getAttribute('data-idx'));
                const fieldToDelete = currentFormState.fields[idx];
                currentFormState.fields.splice(idx, 1);
                
                if (fieldToDelete && fieldToDelete.id === selectedFieldId) {
                    selectedFieldId = null;
                    document.getElementById('vgt-no-field-selected').style.display = 'block';
                    document.getElementById('vgt-field-properties-form').style.display = 'none';
                }

                renderCanvas();
            });
        });

        const placeholder = canvas.querySelector('.vgt-canvas-placeholder') || document.querySelector('.vgt-canvas-placeholder');
        if (placeholder) {
            placeholder.style.display = currentFormState.fields.length === 0 ? 'flex' : 'none';
        }
    };

    // Helper to append navigation row to steps
    const appendStepNavigation = (stepWrap, stepIdx, totalSteps) => {
        const stepNav = document.createElement('div');
        stepNav.className = 'vgt-step-navigation';
        stepNav.style.display = 'flex';
        stepNav.style.justifyContent = 'space-between';
        stepNav.style.alignItems = 'center';
        stepNav.style.marginTop = '2rem';
        stepNav.style.gap = '1rem';
        
        let buttonsHTML = '';
        if (stepIdx > 0) {
            buttonsHTML += `<button type="button" class="vgt-fe-btn prev-step" style="background: rgba(255,255,255,0.05); color: #fff; width: auto; padding: 0.8rem 1.5rem; margin-right: auto;" disabled>Back</button>`;
        }
        
        if (stepIdx < totalSteps - 1) {
            buttonsHTML += `<button type="button" class="vgt-fe-btn next-step" style="width: auto; padding: 0.8rem 2rem; margin-left: auto;" disabled>Continue</button>`;
        } else {
            const btnTxt = currentFormState.settings.button_text || 'Initialize Encryption';
            buttonsHTML += `
                <button type="button" class="vgt-fe-btn vgt-submit-btn" style="width: auto; padding: 0.8rem 2rem; margin-left: auto;" disabled>
                    <span class="btn-text">${escapeHtml(btnTxt)}</span>
                </button>
            `;
        }
        stepNav.innerHTML = buttonsHTML;
        stepWrap.appendChild(stepNav);
    };

    // Save Form Configuration
    const saveBuilderBtn = document.getElementById('vgt-btn-builder-save');
    if (saveBuilderBtn) {
        saveBuilderBtn.addEventListener('click', async () => {
            const title = document.getElementById('vgt-builder-title').value.trim();
            if (!title) {
                showToast('Bitte einen Formular-Titel eingeben.', false);
                return;
            }

            saveBuilderBtn.disabled = true;
            saveBuilderBtn.innerText = 'SAVING CONFIGURATION...';

            currentFormState.title = title;
            currentFormState.type = document.getElementById('vgt-builder-type').value;

            const formData = new FormData();
            formData.append('action', 'vgt_save_form_builder');
            formData.append('security', window.vgtAdminParams?.saveNonce);
            formData.append('form_id', currentFormState.id);
            formData.append('title', currentFormState.title);
            formData.append('type', currentFormState.type);
            formData.append('config', JSON.stringify(currentFormState));

            try {
                const response = await fetch(window.vgtAdminParams.ajaxUrl, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.data.message || 'Form configuration stored successfully.', true);
                    currentFormState.id = result.data.form_id;
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showToast(result.data.message || 'Failed to store.', false);
                }
            } catch (error) {
                showToast('Communication fault with security server.', false);
            } finally {
                saveBuilderBtn.innerText = 'Save Configuration';
                saveBuilderBtn.disabled = false;
            }
        });
    }

    // Toggle Workspace Open: Edit/Create Form
    const openBuilderWorkspace = (formObj = null) => {
        if (formObj) {
            currentFormState = JSON.parse(formObj.config);
            currentFormState.id = parseInt(formObj.id);
            document.getElementById('vgt-builder-title').value = formObj.title;
            document.getElementById('vgt-builder-type').value = formObj.type;

            // Load design settings into right panel inputs
            designTheme.value = currentFormState.settings.theme || 'dark';
            designGoldAccent.value = currentFormState.settings.gold_accent || '#d4af37';
            designBgColor.value = currentFormState.settings.background_color || '#030303';
            designTextColor.value = currentFormState.settings.text_color || '#f9fafb';
            designBgImage.value = currentFormState.settings.background_image || '';
            designBtnText.value = currentFormState.settings.button_text || 'Initialize Encryption';
            designBorderRadius.value = currentFormState.settings.border_radius || '8px';
            designPadding.value = currentFormState.settings.padding || '3rem';
            designWidth.value = currentFormState.settings.width || '780px';
        } else {
            // Default reset state for new forms
            currentFormState = {
                id: 0,
                title: '',
                type: 'form',
                fields: [],
                settings: {
                    theme: 'dark',
                    gold_accent: '#d4af37',
                    background_color: '#030303',
                    text_color: '#f9fafb',
                    background_image: '',
                    button_text: 'Initialize Encryption',
                    border_radius: '8px',
                    padding: '3rem',
                    width: '780px',
                    subtitle: 'End-to-End Encrypted Tunnel'
                }
            };
            document.getElementById('vgt-builder-title').value = '';
            document.getElementById('vgt-builder-type').value = 'form';

            designTheme.value = 'dark';
            designGoldAccent.value = '#d4af37';
            designBgColor.value = '#030303';
            designTextColor.value = '#f9fafb';
            designBgImage.value = '';
            designBtnText.value = 'Initialize Encryption';
            designBorderRadius.value = '8px';
            designPadding.value = '3rem';
            designWidth.value = '780px';
        }

        selectedFieldId = null;
        document.getElementById('vgt-no-field-selected').style.display = 'block';
        document.getElementById('vgt-field-properties-form').style.display = 'none';

        applyLiveStyles();
        renderCanvas();
        switchTab('vgt-sec-builder');
    };

    const createFormBtn = document.getElementById('vgt-btn-create-form');
    if (createFormBtn) {
        createFormBtn.addEventListener('click', () => openBuilderWorkspace());
    }

    document.querySelectorAll('.edit-form').forEach(btn => {
        btn.addEventListener('click', () => {
            const formObj = {
                id: btn.getAttribute('data-id'),
                title: btn.getAttribute('data-title'),
                type: btn.getAttribute('data-type'),
                config: btn.getAttribute('data-config')
            };
            openBuilderWorkspace(formObj);
        });
    });

    const builderBackBtn = document.getElementById('vgt-btn-builder-back');
    if (builderBackBtn) {
        builderBackBtn.addEventListener('click', () => switchTab('vgt-sec-forms'));
    }

    // Delete Form from Directory
    document.querySelectorAll('.delete-form').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.getAttribute('data-id');
            if (!confirm('CRITICAL WARNING:\n\nThis form structure AND ALL ITS SECURED SUBMISSIONS will be permanently purged.\n\nProceed?')) return;

            const formData = new FormData();
            formData.append('action', 'vgt_delete_form');
            formData.append('security', window.vgtAdminParams?.saveNonce);
            formData.append('form_id', id);

            try {
                const response = await fetch(window.vgtAdminParams.ajaxUrl, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.data.message || 'Purged successfully.', true);
                    const row = document.querySelector(`tr[data-form-row-id="${id}"]`);
                    if (row) row.remove();
                } else {
                    showToast('Purging failed.', false);
                }
            } catch (error) {
                showToast('Failed to communicate with DB.', false);
            }
        });
    });

    /* ==============================================================================
     * FORMS SUBMISSIONS VIEWER & RAM DECRYPTION
     * ============================================================================== */
    let currentActiveFormForSubs = null;

    const loadSubmissions = async (formId, formTitle, page = 1) => {
        currentActiveFormForSubs = { id: formId, title: formTitle };
        
        // Find form configurations from edit button to fetch structure fields
        const editBtn = document.querySelector(`.edit-form[data-id="${formId}"]`);
        if (!editBtn) return;
        const configObj = JSON.parse(editBtn.getAttribute('data-config'));
        const fields = configObj.fields || [];

        // Dynamic headers computation (exclude step_break, image, video blocks)
        const headersRow = document.getElementById('vgt-subs-table-headers');
        headersRow.innerHTML = '';
        
        const activeFields = fields.filter(f => f.type !== 'step_break' && f.type !== 'image' && f.type !== 'video' && f.type !== 'heading' && f.type !== 'paragraph');

        let headersHTML = `<th>Eingegangen am</th>`;
        activeFields.forEach(f => {
            headersHTML += `<th>${f.label}</th>`;
        });
        headersHTML += `<th>Host IP (Socket)</th>`;
        headersHTML += `<th class="text-right">Purge</th>`;
        headersRow.innerHTML = headersHTML;

        // Fetch submissions via AJAX (RAM decryption endpoint)
        const tbody = document.getElementById('vgt-subs-table-body');
        tbody.innerHTML = `<tr><td colspan="${activeFields.length + 3}" class="vgt-mono" style="text-align:center; padding:3rem;">Decrypting packets inside memory (RAM)...</td></tr>`;

        const formData = new FormData();
        formData.append('action', 'vgt_get_submissions');
        formData.append('security', window.vgtAdminParams?.saveNonce);
        formData.append('form_id', formId);
        formData.append('paged', page);

        try {
            const response = await fetch(window.vgtAdminParams.ajaxUrl, {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            if (result.success) {
                const subs = result.data.submissions || [];
                tbody.innerHTML = '';

                if (subs.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="${activeFields.length + 3}" class="vgt-mono" style="text-align:center; padding:3rem; color:var(--vgt-text-muted);">No records registered.</td></tr>`;
                    return;
                }

                subs.forEach(sub => {
                    const row = document.createElement('tr');
                    row.setAttribute('data-sub-row-id', sub.id);
                    
                    let cellsHTML = `<td class="vgt-mono vgt-title-xs" style="color:var(--vgt-text-muted);">${escapeHtml(sub.created_at)}</td>`;
                    
                    activeFields.forEach(f => {
                        const cellVal = sub.payload ? sub.payload[f.id] : '';
                        
                        if (cellVal && typeof cellVal === 'object' && cellVal.type === 'file_upload') {
                            // Render link for file uploads
                            cellsHTML += `<td><a href="${escapeHtml(cellVal.url)}" target="_blank" class="vgt-link">${escapeHtml(cellVal.name)}</a></td>`;
                        } else {
                            cellsHTML += `<td>${escapeHtml(String(cellVal || '-'))}</td>`;
                        }
                    });

                    cellsHTML += `<td class="vgt-mono">${escapeHtml(sub.ip_socket)}</td>`;
                    cellsHTML += `
                        <td class="text-right">
                            <button type="button" class="vgt-btn-danger delete-sub" data-sub-id="${escapeHtml(String(sub.id))}">
                                🗑️
                            </button>
                        </td>
                    `;
                    row.innerHTML = cellsHTML;

                    tbody.appendChild(row);
                });

                // Setup sub delete buttons
                tbody.querySelectorAll('.delete-sub').forEach(btn => {
                    btn.addEventListener('click', async () => {
                        const subId = btn.getAttribute('data-sub-id');
                        if (!confirm('CRITICAL WARNING:\n\nThis record will be permanently purged and mathematically wiped from the database.\n\nProceed?')) return;

                        const subFormData = new FormData();
                        subFormData.append('action', 'vgt_delete_submission');
                        subFormData.append('security', window.vgtAdminParams?.saveNonce);
                        subFormData.append('submission_id', subId);

                        try {
                            const subResponse = await fetch(window.vgtAdminParams.ajaxUrl, {
                                method: 'POST',
                                body: subFormData
                            });
                            const subResult = await subResponse.json();
                            if (subResult.success) {
                                showToast(subResult.data.message || 'Wiped.', true);
                                const row = tbody.querySelector(`tr[data-sub-row-id="${subId}"]`);
                                if (row) row.remove();
                            } else {
                                showToast('Purging failed.', false);
                            }
                        } catch (err) {
                            showToast('Failed to communicate with DB.', false);
                        }
                    });
                });

                // Submissions Pagination
                const pagContainer = document.getElementById('vgt-subs-pagination');
                pagContainer.innerHTML = '';
                if (result.data.pages > 1) {
                    for (let i = 1; i <= result.data.pages; i++) {
                        const activeCls = (i === result.data.current) ? 'vgt-page-active' : '';
                        const pagBtn = document.createElement('button');
                        pagBtn.className = `vgt-page-link ${activeCls}`;
                        pagBtn.innerText = i;
                        pagBtn.addEventListener('click', () => {
                            loadSubmissions(formId, formTitle, i);
                        });
                        pagContainer.appendChild(pagBtn);
                    }
                }
            } else {
                tbody.innerHTML = `<tr><td colspan="${activeFields.length + 3}" class="vgt-mono text-red" style="text-align:center; padding:3rem;">Failed to decrypt payload records. GCM integrity check failed.</td></tr>`;
            }
        } catch (error) {
            tbody.innerHTML = `<tr><td colspan="${activeFields.length + 3}" class="vgt-mono text-red" style="text-align:center; padding:3rem;">Connection architecture error. Decryption halted.</td></tr>`;
        }
    };

    document.querySelectorAll('.view-subs').forEach(btn => {
        btn.addEventListener('click', () => {
            const formId = btn.getAttribute('data-id');
            const formTitle = btn.getAttribute('data-title');
            document.getElementById('vgt-subs-title-header').innerText = `Safe-Vault: ${formTitle}`;
            loadSubmissions(formId, formTitle, 1);
            switchTab('vgt-sec-submissions');
        });
    });

    const subsBackBtn = document.getElementById('vgt-btn-subs-back');
    if (subsBackBtn) {
        subsBackBtn.addEventListener('click', () => switchTab('vgt-sec-forms'));
    }
});