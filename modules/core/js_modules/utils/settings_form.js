const initializedSettingsForms = new WeakSet();

function setupSettingsForm(form) {
    if (!form || initializedSettingsForms.has(form)) {
        return;
    }
    initializedSettingsForms.add(form);

    const initialValues = new Map();
    const valueOf = (element) => {
        if (element.type === 'checkbox' || element.type === 'radio') {
            return element.checked;
        }
        if (element.tagName === 'SELECT' && element.multiple) {
            return Array.from(element.selectedOptions, option => option.value);
        }
        return element.value;
    };

    Array.from(form.elements).forEach(element => {
        if (element.name && element.name !== 'hm_page_key' && element.name !== 'save_settings') {
            initialValues.set(element, JSON.stringify(valueOf(element)));
        }
    });

    form.addEventListener('formdata', event => {
        const data = event.formData;
        if (!data.has('save_settings')) {
            return;
        }

        const fields = new Map();
        Array.from(form.elements).forEach(element => {
            if (!initialValues.has(element)) {
                return;
            }
            if (!fields.has(element.name)) {
                fields.set(element.name, []);
            }
            fields.get(element.name).push(element);
        });

        fields.forEach((elements, name) => {
            const changed = elements.some(element => initialValues.get(element) !== JSON.stringify(valueOf(element)));
            if (!changed) {
                data.delete(name);
                return;
            }
            if (elements.length === 1 && elements[0].type === 'checkbox') {
                data.set(name, elements[0].checked ? elements[0].value : '0');
            }
        });

        // When enabling 2FA, backup codes will not record any changes as they are auto-generated, but they should be submitted anyway.
        const twoFactor = form.elements.namedItem('2fa_enable');
        if (twoFactor && twoFactor.checked && data.has('2fa_enable')) {
            data.delete('2fa_backup_codes[]');
            Array.from(form.elements).forEach(element => {
                if (element.name === '2fa_backup_codes[]' && !element.disabled) {
                    data.append(element.name, element.value);
                }
            });
        }
    });
}
