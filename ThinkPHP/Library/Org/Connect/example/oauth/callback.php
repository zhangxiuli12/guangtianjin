<?php
require_once("../../API/qqConnectAPI.class.php");
$qc = new QC();
echo $qc->qq_callback();
echo $qc->get_openid();
