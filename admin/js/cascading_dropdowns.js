// Cascading dropdowns functionality - loaded dynamically
document.addEventListener('DOMContentLoaded', function() {
    const departmentSelect = document.getElementById('department_id');
    const classSelect = document.getElementById('class_id');
    const sectionSelect = document.getElementById('section_id');

    departmentSelect.addEventListener('change', function() {
        const departmentId = this.value;
        if (departmentId) {
            // Fetch classes for the selected department
            fetch('get_classes.php?department_id=' + departmentId)
                .then(response => response.json())
                .then(data => {
                    classSelect.innerHTML = '<option value="">Select Class</option>';
                    data.forEach(cls => {
                        const option = document.createElement('option');
                        option.value = cls.class_id;
                        option.textContent = cls.name;
                        classSelect.appendChild(option);
                    });
                    classSelect.disabled = false;
                    sectionSelect.disabled = true;
                    sectionSelect.innerHTML = '<option value="">Select Section</option>';
                })
                .catch(error => console.error('Error fetching classes:', error));
        } else {
            classSelect.disabled = true;
            sectionSelect.disabled = true;
            classSelect.innerHTML = '<option value="">Select Class</option>';
            sectionSelect.innerHTML = '<option value="">Select Section</option>';
        }
    });

    classSelect.addEventListener('change', function() {
        const departmentId = departmentSelect.value;
        const classId = this.value;
        if (departmentId && classId) {
            // Fetch sections for the selected department and class
            fetch('get_sections.php?department_id=' + departmentId)
                .then(response => response.json())
                .then(data => {
                    sectionSelect.innerHTML = '<option value="">Select Section</option>';
                    data.forEach(sec => {
                        const option = document.createElement('option');
                        option.value = sec.section_id;
                        option.textContent = sec.name;
                        sectionSelect.appendChild(option);
                    });
                    sectionSelect.disabled = false;
                })
                .catch(error => console.error('Error fetching sections:', error));
        } else {
            sectionSelect.disabled = true;
            sectionSelect.innerHTML = '<option value="">Select Section</option>';
        }
    });
});
