$(document).ready(function () {

    // Inisialisasi semua tabel yang mempunyai class .datatable
    $('.datatable').each(function () {

        if (!$.fn.DataTable.isDataTable(this)) {

            $(this).DataTable({
                pageLength: 10,
                responsive: false, // Set to false so DataTables does not inject dtr-control column or shift th/td
                autoWidth: false,
                dom: "<'dt-controls-top'<'dt-length-container'l><'dt-search-container'f>>" +
                     "t" +
                     "<'dt-controls-bottom'<'dt-info-container'i><'dt-paginate-container'p>>",
                language: {
                    lengthMenu: "Tampilkan _MENU_ data",
                    info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
                    infoEmpty: "Menampilkan 0 data",
                    infoFiltered: "(disaring dari _MAX_ total data)",
                    zeroRecords: "Tidak ada data yang ditemukan",
                    search: "Cari:",
                    paginate: {
                        first: '<i class="fas fa-angle-double-left"></i>',
                        previous: '<i class="fas fa-angle-left"></i>',
                        next: '<i class="fas fa-angle-right"></i>',
                        last: '<i class="fas fa-angle-double-right"></i>'
                    }
                }
            });

        }

    });

});