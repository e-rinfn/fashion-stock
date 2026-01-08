<?php
include_once __DIR__ . '/../../config/config.php';

?>

<!-- [Page Specific JS] start -->
<script src="<?= $base_url ?>/assets/js/plugins/apexcharts.min.js"></script>
<script src="<?= $base_url ?>/assets/js/pages/dashboard-default.js"></script>
<!-- [Page Specific JS] end -->
<!-- Required Js -->
<script src="<?= $base_url ?>/assets/js/plugins/popper.min.js"></script>
<script src="<?= $base_url ?>/assets/js/plugins/simplebar.min.js"></script>
<script src="<?= $base_url ?>/assets/js/plugins/bootstrap.min.js"></script>
<script src="<?= $base_url ?>/assets/js/fonts/custom-font.js"></script>
<script src="<?= $base_url ?>/assets/js/pcoded.js"></script>
<script src="<?= $base_url ?>/assets/js/plugins/feather.min.js"></script>

<script>
    layout_change("light");
</script>

<script>
    change_box_container("false");
</script>

<script>
    layout_rtl_change("false");
</script>

<script>
    preset_change("preset-1");
</script>

<script>
    font_change("Public-Sans");
</script>

<!-- [PWA Service Worker Registration] -->
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('<?= $base_url ?>/sw.js')
                .then((registration) => {
                    console.log('ServiceWorker registered: ', registration.scope);
                })
                .catch((error) => {
                    console.log('ServiceWorker registration failed: ', error);
                });
        });
    }
</script>