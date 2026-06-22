<?php
// logout_silent.php ናይ browser -> ንከይሰርሕ ይገብር።
session_start();
session_unset();
session_destroy();
echo "session_killed";
?>