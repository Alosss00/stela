$(document).ready(function () {

    console.log("JQUERY =", typeof $);

    console.log("DATATABLE =", typeof $.fn.DataTable);

    $('#employeesTable').DataTable({
        pageLength:10,
        responsive:true
    });

});