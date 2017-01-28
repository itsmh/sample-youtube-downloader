<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
session_start();
if(isset($_POST['delete'])) {
    unset($_SESSION['videos'][$_POST['cid']][$_POST['vid']]);
}else {
    if( !isset($_SESSION['videos'] ) ) {
        $_SESSION['videos'] = [];
    }
    if( !isset($_SESSION['videos'][$_POST['cid']]) ) {
        $_SESSION['videos'][$_POST['cid']] = [];
    }
    $_SESSION['videos'][$_POST['cid']][$_POST['vid']] = 1;
}
