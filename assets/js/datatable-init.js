$(document).ready(function () {

    console.log("JQUERY =", typeof $);
    console.log("DATATABLE =", typeof $.fn.DataTable);

    // Inisialisasi semua tabel yang mempunyai class .datatable
    $('.datatable').each(function () {

        if (!$.fn.DataTable.isDataTable(this)) {

            $(this).DataTable({
                pageLength: 10,
                responsive: true,
                autoWidth: false
            });

        }

    });

});