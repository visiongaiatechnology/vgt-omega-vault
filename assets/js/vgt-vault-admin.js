/**
 * VGT OMEGA VAULT: Decoupled Single-Page Navigation & Dynamic SVG Analytics
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

        // Retrieve telemetry passed down via localized system parameter
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

        const maxVal = Math.max(...data.map(d => d.count), 5); // Fallback grid height
        const stepX = (width - paddingLeft - paddingRight) / Math.max(data.length - 1, 1);
        
        let points = [];
        let gridlines = '';
        let xLabels = '';

        // Generate Y-axis reference indicators
        for (let i = 0; i <= 4; i++) {
            const yVal = Math.round((maxVal / 4) * i);
            const yPos = height - paddingBottom - ((height - paddingTop - paddingBottom) / 4) * i;
            gridlines += `<line x1="${paddingLeft}" y1="${yPos}" x2="${width - paddingRight}" y2="${yPos}" class="vgt-chart-gridline" />`;
            gridlines += `<text x="${paddingLeft - 10}" y="${yPos + 4}" text-anchor="end" class="vgt-chart-text">${yVal}</text>`;
        }

        // Draw plotting coordinates
        data.forEach((item, index) => {
            const x = paddingLeft + index * stepX;
            const y = height - paddingBottom - ((item.count / maxVal) * (height - paddingTop - paddingBottom));
            points.push({ x, y });

            // Truncate dates for optimal tech representation
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

    // Trigger chart rendering instantly on initial load
    renderSvgAnalytics();

    // 3. Live Client-Side Searching / Filter of Table
    const searchInput = document.getElementById('vgt-vault-search');
    const tableRows = document.querySelectorAll('.vgt-table tbody tr');

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // 4. Client-side Toast Event Dispatcher
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

    // 5. Asynchronous Config Post Controller (Zero Reload)
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

    // Window Resize Observer for the dynamic chart to maintain aspect-ratio
    window.addEventListener('resize', renderSvgAnalytics);
});