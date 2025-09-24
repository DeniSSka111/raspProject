document.addEventListener('DOMContentLoaded', function () {
    var addBtn = document.getElementById('add-button');
    var addForm = document.getElementById('add-form');

    if (addBtn) {
        addBtn.addEventListener('click', function (e) {
            e.preventDefault();
            addBtn.style.display = 'none';
            if (addForm) addForm.style.display = 'block';
        });
    }

    var cancel = document.querySelector('.cancel-button');
    if (cancel) {
        cancel.addEventListener('click', function (e) {
            e.preventDefault();
            if (addForm) addForm.style.display = 'none';
            if (addBtn) addBtn.style.display = 'inline-block';
        });
    }
});
