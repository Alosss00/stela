$(document).ready(function () {

    console.log("JQUERY =", typeof $);
    console.log("DATATABLE =", typeof $.fn.DataTable);

    // Employees
    if ($('#employeesTable').length) {
        $('#employeesTable').DataTable({
            pageLength: 10,
            responsive: true,
            autoWidth: false
        });
    }

    // Semua tabel yang memakai class .datatable
    if ($('.datatable').length) {
        $('.datatable').DataTable({
            pageLength: 10,
            responsive: true,
            autoWidth: false
        });
    }

});