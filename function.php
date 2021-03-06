<?php

use Hcode\Model\Cart;
use Hcode\Model\User;

function formatPrice($vlprice){

    if (!$vlprice > 0) $vlprice = 0;

    return number_format((float)$vlprice, 2, ",", ".");

}

function formatDate($date){

    return date('d/m/Y', strtotime($date));

}

function checkLogin($inadmin = true){

    return User::checkLogin($inadmin);

}

function getUserName(){

    $user = User::getFromSession();

    return $user->getdesperson();

}

function getCartNrQtd() {

    $cart = Cart::getFromSession();

    $total = $cart->getProductsTotals();

    return $total['nrqtd'];

}

function getCartVlSubTotal() {

    try {

        $cart = Cart::getFromSession();
        $total = $cart->getProductsTotals();
        
        return formatPrice($total['vlprice']);

    } catch (\Throwable $th) {
        
        return formatPrice("0");

    }

}

?>