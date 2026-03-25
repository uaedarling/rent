<?php
exec("cd /home/tranpphd/site.transformergt.com/rent && git pull origin main 2>&1", $output);
print_r($output);
?>
