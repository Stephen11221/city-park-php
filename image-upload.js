'use strict';
document.querySelectorAll('[data-photo-input]').forEach((input) => {
    const field = input.closest('.form-field');
    const preview = field.querySelector('[data-photo-preview]');
    const remove = field.querySelector('[name="remove_image"]');
    const original = preview.getAttribute('src');
    let objectUrl;
    input.addEventListener('change', () => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        const file = input.files[0];
        if (file && ['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            objectUrl = URL.createObjectURL(file);
            preview.src = objectUrl;
            preview.hidden = false;
            if (remove) remove.checked = false;
        } else {
            if (original) preview.src = original;
            else preview.removeAttribute('src');
            preview.hidden = !original;
        }
    });
});
