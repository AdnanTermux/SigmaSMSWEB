#!/bin/bash
# Sigma SMS A2P — Download all required frontend assets
# Run this once from the project root: bash download_assets.sh

BASE="/home/claude/sigma_sms/assets"
CSS="$BASE/css"
JS="$BASE/js"

echo "Downloading CSS assets..."

# Bootstrap 5
curl -sL "https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" -o "$CSS/bootstrap.min.css"
curl -sL "https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" -o "$JS/bootstrap.bundle.min.js"

# Remix Icons
curl -sL "https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.min.css" -o "$CSS/remixicon.min.css"
curl -sL "https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.woff2" -o "$BASE/fonts/remixicon.woff2" 2>/dev/null || true

# jQuery
curl -sL "https://code.jquery.com/jquery-3.7.1.min.js" -o "$JS/jquery.min.js"

# DataTables
curl -sL "https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.min.css" -o "$CSS/dataTables.bootstrap5.min.css"
curl -sL "https://cdn.datatables.net/2.0.8/js/jquery.dataTables.min.js" -o "$JS/jquery.dataTables.min.js"
curl -sL "https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.min.js" -o "$JS/dataTables.bootstrap5.min.js"

# DataTables Buttons
curl -sL "https://cdn.datatables.net/buttons/3.0.2/js/dataTables.buttons.min.js" -o "$JS/dataTables.buttons.min.js"
curl -sL "https://cdn.datatables.net/buttons/3.0.2/js/buttons.html5.min.js" -o "$JS/buttons.html5.min.js"
curl -sL "https://cdn.datatables.net/buttons/3.0.2/js/buttons.print.min.js" -o "$JS/buttons.print.min.js"
curl -sL "https://cdn.datatables.net/buttons/3.0.2/css/buttons.bootstrap5.min.css" -o "$CSS/buttons.bootstrap5.min.css"

# JSZip & PDFMake
curl -sL "https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js" -o "$JS/jszip.min.js"
curl -sL "https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js" -o "$JS/pdfmake.min.js"
curl -sL "https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js" -o "$JS/vfs_fonts.js"

# ApexCharts
curl -sL "https://cdn.jsdelivr.net/npm/apexcharts@3.52.0/dist/apexcharts.min.js" -o "$JS/apexcharts.min.js"

# Select2
curl -sL "https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" -o "$CSS/select2.min.css"
curl -sL "https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js" -o "$JS/select2.min.js"
curl -sL "https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" -o "$CSS/select2-bootstrap-5-theme.min.css"

# Flatpickr
curl -sL "https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css" -o "$CSS/flatpickr.min.css"
curl -sL "https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js" -o "$JS/flatpickr.min.js"

echo "All assets downloaded successfully!"
echo ""
echo "Remixicon font — if icons don't load, update the font path in remixicon.min.css to use a CDN URL,"
echo "or download remixicon fonts into assets/fonts/ directory."
