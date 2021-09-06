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

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Subcuenta;

class AsientoPredefinido extends ModelClass
{

    use ModelTrait;

    /**
     * @var string
     */
    public $concepto;

    /**
     * @var string
     */
    public $descripcion;

    /**
     * @var int
     */
    public $id;

    public function generate(array $form, string &$mensajeError): Asiento
    {
        $asiento = new Asiento(); // Creamos un modelo asiento
        $asiento->idempresa = $form["idempresa"]; // Rellenamos al asiento el idempresa del form
        $asiento->setDate($form["fecha"]); // Rellenamos al asiento el campo fecha
        $asiento->concepto = $this->concepto; // Asignamos al concepto el campo concepto de la cabecera del asiento predefinido
        
        if (false === $asiento->save()) {
            $mensajeError = 'No se pudo crear la cabecera del asiento.';

            // Devolvemos el asiento incompleto
            return $asiento;
        }

        $variables = $this->getVariables(); // Traemos en un array todas las variables creadas para el asiento predefinido.
        $lines = $this->getLines();

        // Primero comprobaremos la cantidad de variables creadas en las líneas del asiento predefinido
        // Luego comprobaremos la cantidad de variables creadas (pestaña Generar del asiento predefinido)
        // Ambas cantidades debe de ser igual, además debemos de comprobar si todas las variables tienen su valor introducido para el asiento que vamos acrear
        if ($this->checkLinesWithVariables($variables, $lines) === false) {
            $mensajeError = 'Sin contar la variable Z (Descuadre del asiento), en la pestaña de Lineas hay más variables a usar que las que se han creado en la pestaña Variables.'; 

            // Devolvemos el asiento incompleto
            $asiento->delete(); // Borramos todo el asiento, incluidas las líneas que se hubieran generado correctamente
            return $asiento; // Devolvemos el asiento vacío y no continua creandole líneas
        }
        
        $saldo = 0.0;
        
        // Recorremos todas las líneas/partidas del asiento predefinido para crearlas en el asiento que estamos creando
        foreach ($lines as $line) {
            $newLine = $asiento->getNewLine(); // Crea la línea pero con los campos vacíos
            
            
            // Creamos la subcuenta sustituyendo las variables que tuviera por su valor
            $codsubcuenta = '';
            if ($this->varLineReplace($saldo, 'S', $codsubcuenta, $line->codsubcuenta, $form, $variables, $mensajeError) === false) {
                // Hay variables que todavía no se han creado
                $asiento->delete(); // Borramos todo el asiento, incluidas las líneas que se hubieran generado correctamente
                return $asiento; // Devolvemos el asiento vacío y no continua creandole líneas
            }
            
            // Creamos el debe sustituyendo las variables que tuviera por su valor
            $debe = 0.0;
            if ($this->varLineReplace($saldo, 'D', $debe, $line->debe, $form, $variables, $mensajeError) === false) {
                // Hay variables que todavía no se han creado
                $asiento->delete(); // Borramos todo el asiento, incluidas las líneas que se hubieran generado correctamente
                return $asiento; // Devolvemos el asiento vacío y no continua creandole líneas
            }
            
            // Creamos el haber sustituyendo las variables que tuviera por su valor
            $haber = 0.0;
            if ($this->varLineReplace($saldo, 'H', $haber, $line->haber, $form, $variables, $mensajeError) === false) {
                // Hay variables que todavía no se han creado
                $asiento->delete(); // Borramos todo el asiento, incluidas las líneas que se hubieran generado correctamente
                return $asiento; // Devolvemos el asiento vacío y no continua creandole líneas
            }
            
            $saldo += $debe - $haber;
            
            // Una vez calculados bien los valores con variables, los asignamos a la línea
            $subcuenta = $newLine->getSubcuenta($codsubcuenta);
            $newLine->setAccount($subcuenta);
            $newLine->concepto = $line->concepto;
            $newLine->debe = $debe;
            $newLine->haber = $haber;
            
            if (false === $newLine->save()) {
                $mensajeError = 'No se pudo grabar la línea del asiento con la subucuenta ' . $codsubcuenta; 
                
                // No se pudo grabar la línea
                $asiento->delete(); // Borramos todo el asiento, incluidas las líneas que se hubieran generado correctamente
                return $asiento; // Devolvemos el asiento vacío y no continua creandole líneas
            }
        }

        
        return $asiento;
    }

    public function getLines(): array
    {
        $line = new AsientoPredefinidoLinea();
        $where = [new DataBaseWhere("idasientopre", $this->id)];
        return $line->all($where);
    }

    protected function getVariables(): array
    {
        // Traemos todas las variables del asiento predefinido
        $variable = new AsientoPredefinidoVariable(); // Creamos el modelo $variable -> AsientoPredefinidoVariable()
        $where = [new DataBaseWhere("idasientopre", $this->id)]; // Filtramos por el id de este asiento predefinido
        
        return $variable->all($where); // Devolvemos el array de todas las variables creadas para este asiento predefinido
    }
    
    public static function primaryColumn()
    {
        return "id";
    }

    public static function tableName()
    {
        return "asientospre";
    }

    public function test()
    {
        $utils = $this->toolBox()->utils();
        $this->concepto = $utils->noHtml($this->concepto);
        $this->descripcion = $utils->noHtml($this->descripcion);

        return parent::test();
    }

    public function url(string $type = 'auto', string $list = 'ListAsiento?activetab=List'): string
    {
        return parent::url($type, $list);
    }
    
    protected function addToVariables(string $toCheck, &$array)
    {
        // Dejamos sólo los caracteres aceptados ... letras en mayúsculas (A-Z).
        $caracteresAceptados = preg_replace("/[^A-Z\s]/", "", $toCheck);

        for ($i = 0; $i < strlen($caracteresAceptados); $i++) {
            if ($caracteresAceptados[$i] <> 'Z') { // La Z ... NO ES UNA VARIABLE a crear su valor en pestaña Generar de Asientos Predefinidos, AUNQUE se usará para poner el valor del descuadre del asiento
                $existeEnArray = false;
                foreach ($array as $valor) {
                    if ($valor === $caracteresAceptados[$i]) {
                        $existeEnArray = true;
                        break;
                    }
                }
                
                if ($existeEnArray === false) {
                    $array[] = $caracteresAceptados[$i];
                }
            }
        }
    }
    
    protected function checkLinesWithVariables(array $variables, array $lines): bool
    {
        $aDevolver = true;
        
        $variablesEnLineas = [];
        $variablesEnVariables = [];
        
        // Contamos la cantidad de variables usadas en pestaña Líneas de Asientos Predefinidos
        foreach ($lines as $line) {
            $this->addToVariables($line->codsubcuenta, $variablesEnLineas);
            $this->addToVariables($line->debe, $variablesEnLineas);
            $this->addToVariables($line->haber, $variablesEnLineas);
        }
        
        // Contamos la cantidad de variables usadas en pestaña Variables de Asientos Predefinidos
        foreach ($variables as $variable) {
            if ($variable->codigo <> 'Z') { // La Z ... NO ES UNA VARIABLE a crear su valor en pestaña Generar de Asientos Predefinidos, AUNQUE se usará para poner el valor del descuadre del asiento
                $existeEnArray = false;
                foreach ($variablesEnVariables as $valor) {
                    if ($valor === $variable->codigo) {
                        $existeEnArray = true;
                        break;
                    }
                }
                
                if ($existeEnArray === false) {
                    $variablesEnVariables[] = $variable->codigo;
                }
            }
        }

        // Comprobamos la cantidad de variables de pestaña Líneas con la cantidad de variables de pestaña Variables
        return count($variablesEnLineas) === count($variablesEnVariables);
    }
    
    protected function varLineReplace( float $saldo, string $tipoSinVariable, string &$sinVariable, string $conVariable, array $form, array $variables, string &$mensajeError): bool
    {
        /**
         * A reemplazar
        foreach($variables as $var) {
            $remplazar = str_replace($var->codigo, $form['var_'.$var->codigo], $reemplazar);
        }

        $remplazar = str_replace('Z', $saldo, $reemplazar);
        */

        // Recorremos cada uno de los caracteres para ver si es variable o no
        for ($i = 0; $i < strlen($conVariable); $i++) {
            $caracter = preg_replace("/[^A-Z\s]/", "", $conVariable[$i]); // Sólo permitimos letras en mayúsculas
            if (strlen($caracter) <= 0) {
                // No es una variable, así que añadimos el caracter como parte de la subcuenta
                $sinVariable .= $conVariable[$i];
                continue;
            }

            // Es una variable, así que sustituimos el caracter por el valor de la variable que tenemos en $form
            // Pero antes tenemos que recorrer todas las variables para ver si está creada, si no lo estuviera sacar mensaje de ello
            $laVariableExiste = false;
            
            foreach ($variables as $variable) {
                if ( $conVariable[$i] === $variable->codigo or $conVariable[$i] === 'Z' ) {
                    $laVariableExiste = true;
                    break;
                }
            }

            if ($laVariableExiste === true) {
                if ($conVariable[$i] <> 'Z') {
                    $sinVariable .= $form['var_' . $caracter];
                } else if ($tipoSinVariable <> 'S') {
                        settype($saldo, "string"); // Convertimos $resultado en un string
                        $sinVariable .= $saldo;
                } else {
                        $mensajeError = 'En la subucuenta ' . $conVariable . ' ha usado la variable Z que sirve para devolver el descuadre del asiento a crear. Pero sólo se puede usar esta variable en Debe/Haber.'; 
                        return false;
                }
                
                continue;
            }
            
            switch ($tipoSinVariable) { // $tipoSinVariable sólo puede valer 'S', 'D' ó 'H'
                case 'S':
                    $mensajeError = 'En la subucuenta ' . $conVariable; 
                    break;

                case 'D':
                    $mensajeError = 'En el DEBE de la subucuenta ' . $conVariable; 
                    break;
                    
                case 'H':
                    $mensajeError = 'En el HABER de la subucuenta ' . $conVariable; 
                    break;
            }
            
            $mensajeError .= ' hay variables que todavía no se han creado en la pestaña Variables.'; 
            return false; // Salimos sin terminar de sustituir las variables
        }
        
//        if ($tipoSinVariable <> 'S') {
//            $resultado = floatval($sinVariable);
//            settype($resultado, "string"); // Convertimos $resultado en un string
//            $sinVariable = $resultado;
//        }

        return true;
    }

}
