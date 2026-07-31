// STATUS: PLATIN

(() => {
    'use strict';

    const runtime = window.vgtOmegaRuntime;
    if (!runtime || typeof runtime.ajaxUrl !== 'string') {
        return;
    }

    const setStatus = (form, message, state = '') => {
        const status = form.querySelector('.vgt-omega-status');
        if (!(status instanceof HTMLElement)) {
            return;
        }
        status.textContent = message;
        status.dataset.state = state;
    };

    const issueToken = async (form) => {
        const formId = form.dataset.vgtFormId;
        if (!formId) {
            throw new Error('Missing form identifier.');
        }

        const body = new URLSearchParams();
        body.set('action', runtime.tokenAction);
        body.set('form_id', formId);

        const response = await fetch(runtime.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body.toString()
        });

        const json = await response.json();
        const token = json?.data?.token;
        const nonce = json?.data?.nonce;
        if (
            !response.ok ||
            json?.success !== true ||
            typeof token !== 'string' ||
            typeof nonce !== 'string'
        ) {
            throw new Error(runtime.messages.tokenError);
        }

        const tokenSlot = form.elements.namedItem('vgt_request_token');
        const nonceSlot = form.elements.namedItem('vgt_nonce');
        if (!(tokenSlot instanceof HTMLInputElement) || !(nonceSlot instanceof HTMLInputElement)) {
            throw new Error(runtime.messages.tokenError);
        }
        tokenSlot.value = token;
        nonceSlot.value = nonce;
    };

    const validateFiles = (form) => {
        for (const input of form.querySelectorAll('input[type="file"]')) {
            if (!(input instanceof HTMLInputElement) || input.files === null) {
                continue;
            }
            const maxBytes = Number.parseInt(input.dataset.vgtMaxBytes ?? '0', 10);
            for (const file of input.files) {
                if (maxBytes > 0 && file.size > maxBytes) {
                    input.setCustomValidity(runtime.messages.fileTooLarge);
                    input.reportValidity();
                    input.setCustomValidity('');
                    return false;
                }
            }
        }
        return true;
    };

    const currentStep = (form) => {
        const visible = form.querySelector('.vgt-omega-step:not([hidden])');
        return visible instanceof HTMLFieldSetElement ? visible : null;
    };

    const showStep = (form, targetIndex) => {
        const steps = Array.from(form.querySelectorAll('.vgt-omega-step'));
        steps.forEach((step, index) => {
            step.hidden = index !== targetIndex;
        });
    };

    const validateStep = (step) => {
        for (const control of step.querySelectorAll('input, textarea, select')) {
            if (
                control instanceof HTMLInputElement ||
                control instanceof HTMLTextAreaElement ||
                control instanceof HTMLSelectElement
            ) {
                if (!control.reportValidity()) {
                    return false;
                }
            }
        }
        return true;
    };

    const moveStep = (form, direction) => {
        const steps = Array.from(form.querySelectorAll('.vgt-omega-step'));
        const active = currentStep(form);
        const index = active ? steps.indexOf(active) : -1;
        const target = index + direction;
        if (index < 0 || target < 0 || target >= steps.length) {
            return;
        }
        if (direction > 0 && active && !validateStep(active)) {
            return;
        }
        showStep(form, target);
        const focusTarget = steps[target].querySelector('input, textarea, select, button');
        if (focusTarget instanceof HTMLElement) {
            focusTarget.focus();
        }
    };

    const validateForm = (form) => {
        const steps = Array.from(form.querySelectorAll('.vgt-omega-step'));
        for (let stepIndex = 0; stepIndex < steps.length; stepIndex += 1) {
            const controls = steps[stepIndex].querySelectorAll('input, textarea, select');
            for (const control of controls) {
                if (
                    control instanceof HTMLInputElement ||
                    control instanceof HTMLTextAreaElement ||
                    control instanceof HTMLSelectElement
                ) {
                    if (!control.checkValidity()) {
                        showStep(form, stepIndex);
                        control.reportValidity();
                        return false;
                    }
                }
            }
        }

        for (const control of form.querySelectorAll(':scope > input, :scope > label input, :scope > select, :scope > textarea')) {
            if (
                control instanceof HTMLInputElement ||
                control instanceof HTMLTextAreaElement ||
                control instanceof HTMLSelectElement
            ) {
                if (!control.reportValidity()) {
                    return false;
                }
            }
        }

        return true;
    };

    const submitForm = async (form) => {
        if (!validateForm(form) || !validateFiles(form)) {
            return;
        }

        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton instanceof HTMLButtonElement) {
            submitButton.disabled = true;
        }
        setStatus(form, runtime.messages.sending, 'pending');

        try {
            await issueToken(form);
            const response = await fetch(runtime.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                body: new FormData(form),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const json = await response.json();
            const message = typeof json?.data?.message === 'string'
                ? json.data.message
                : runtime.messages.networkError;

            if (!response.ok || json?.success !== true) {
                const errorId = typeof json?.data?.error_id === 'string' ? ` [${json.data.error_id}]` : '';
                throw new Error(message + errorId);
            }

            setStatus(form, message, 'success');
            form.reset();

            showStep(form, 0);
        } catch (error) {
            const message = error instanceof Error ? error.message : runtime.messages.networkError;
            setStatus(form, message, 'error');
        } finally {
            if (submitButton instanceof HTMLButtonElement) {
                submitButton.disabled = false;
            }
        }
    };

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        const next = target.closest('[data-vgt-next]');
        const back = target.closest('[data-vgt-back]');
        const form = target.closest('.vgt-omega-form');

        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        if (next) {
            moveStep(form, 1);
        } else if (back) {
            moveStep(form, -1);
        }
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.classList.contains('vgt-omega-form')) {
            return;
        }
        event.preventDefault();
        void submitForm(form);
    });

})();
