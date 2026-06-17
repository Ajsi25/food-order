// ============================================================
//  ADMIN JS - modal helpers
// ============================================================

function openModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.add('active');
}

function closeModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.remove('active');
}

function openEdit(id, name) {
    document.getElementById('editId').value = id;
    document.getElementById('editName').value = name;
    openModal('editModal');
}

document.addEventListener('click', e => {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(a => setTimeout(() => a.style.display = 'none', 4000));
});
