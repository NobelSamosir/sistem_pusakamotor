<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilempar ketika aturan bisnis SIA/DSS dilanggar
 * (misal: motor sudah terjual, jurnal tidak seimbang).
 * Nanti di-render sebagai respons HTTP 422 berisi pesan ini.
 */
class BusinessRuleException extends RuntimeException
{
}
