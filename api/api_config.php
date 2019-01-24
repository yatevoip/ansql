<?php

$api_version = "v1";
$logs_dir = "/var/log/mmi_api";
$logs_file = (is_writeable($logs_dir)) ? "$logs_dir/requests_log.txt" : null;
$log_status = true;
$api_secret = "mmi_secret";
$cors_origin = "*";
$default_limit = null;
$default_offset = 0;
