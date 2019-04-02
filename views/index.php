<?php
/**
 * Created by IntelliJ IDEA.
 * User: michaelmartins
 * Date: 2019-03-14
 * Time: 10:58
 */

header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

header('Location: ../../../');
exit;
