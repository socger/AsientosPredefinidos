<?php
/**
 * This file is part of AsientoPredefinido plugin for FacturaScripts
 * Copyright (C) 2021 Carlos Garcia Gomez            <carlos@facturascripts.com>
 *                    Jeronimo Pedro Sánchez Manzano <socger@gmail.com>
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Plugins\AsientosPredefinidos\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;

class AsientoPredefinidoLinea extends ModelClass
{
    use ModelTrait;

    /**
     * @var string
     */
    public $codsubcuenta;

//    /**
//     * @var string
//     */
//    public $codcontrapartida;
//
    
    /**
     * @var string
     */
    public $concepto;

    /**
     * @var string
     */
    public $debe;

    /**
     * @var string
     */
    public $haber;

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $idasientopre;

    /**
     * @var int
     */
    public $orden;

    public function clear()
    {
        parent::clear();
        // $this->debe = '0';
        // $this->haber = '0';
        $this->orden = 0;
    }
    
    private function comprobarSubcuenta(string $codsubcuenta) : bool {
        $aDevolver = true;

        // Dejamos sólo los caracteres aceptados ... números(0-9) y letras en mayúsculas (A-Z)
        $caracteresAceptados = preg_replace("/[^A-Z0-9\s]/", "", $codsubcuenta);

        // Comprobamos si introdujo algún caracter no admitido
        if (strlen($caracteresAceptados) <> strlen($codsubcuenta)) {
            $aDevolver = false;
            $this->toolBox()->i18nLog()->error('Para la subcuenta introdujo ' . $codsubcuenta . '. Pero la subcuenta sólo puede tener números(0-9) ó letras en mayúsculas (A-Z)');
        }
        
        // Recorremos todos los caracteres admitidos para ver si hay más de una variable y para ver si han usado la variable Z (es variable de resultados (descuadre del asiento)
        $contadorVariables = 0;
        $hayVariableZ = 0;
        
        for ($i = 0; $i < strlen($caracteresAceptados); $i++) {
            
            $variable = preg_replace("/[^A-Z\s]/", "", $caracteresAceptados[$i]); // Sólo dejamos letras en mayúsculas
            if (strlen($variable) > 0) {
                $contadorVariables .= 1;
            }
            
            if ($variable === 'Z') {
                $hayVariableZ .= 1;
            }
        }

        if ($hayVariableZ > 0) {
            $aDevolver = false;
            $this->toolBox()->i18nLog()->error('Para la subcuenta introdujo ' . $codsubcuenta . '. Pero la subcuenta no puede tener la variable Z (DESCUADRE del asiento)');
        }
        

        if ($contadorVariables > 1) {
            $aDevolver = false;
            $this->toolBox()->i18nLog()->error('Para la subcuenta introdujo ' . $codsubcuenta . '. Pero la subcuenta no puede tener más de una variable (letras en mayúsculas A-Z)');
        }
        
        return $aDevolver;
    }

    public static function primaryColumn()
    {
        return "id";
    }

    public static function tableName()
    {
        return "asientospre_lineas";
    }

    public function test()
    {
        if ($this->comprobarSubcuenta($this->codsubcuenta) === false) {
            return false;
        }
        
        $utils = $this->toolBox()->utils();
        $this->codsubcuenta = $utils->noHtml($this->codsubcuenta);
        $this->concepto = $utils->noHtml($this->concepto);
        $this->debe = $this->toolBox()->utils()->noHtml($this->debe);
        $this->haber = $this->toolBox()->utils()->noHtml($this->haber);
        
        return parent::test();
    }
}
