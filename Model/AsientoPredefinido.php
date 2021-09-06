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

    public function generate(array $form): Asiento
    {
        $asiento = new Asiento(); // Creamos un modelo asiento
        $asiento->idempresa = $form["idempresa"]; // Rellenamos al asiento el idempresa del form
        $asiento->setDate($form["fecha"]); // Rellenamos al asiento el campo fecha
        $asiento->concepto = $this->concepto; // Asignamos al concepto el campo concepto de la cabecera del asiento predefinido
        
        if (false === $asiento->save()) {
            $this->toolBox()->i18nLog()->warning('no-can-create-accounting-entry');
            return $asiento; // Devolvemos el asiento incompleto, vacío.
        }

        $variables = $this->getVariables(); // Traemos en un array todas las variables creadas para el asiento predefinido.
        $lines = $this->getLines();

        // Primero comprobaremos la cantidad de variables creadas en las líneas del asiento predefinido
        // Luego comprobaremos la cantidad de variables creadas (pestaña Generar del asiento predefinido)
        // Ambas cantidades debe de ser igual, además debemos de comprobar si todas las variables tienen su valor introducido para el asiento que vamos acrear
        if ($this->checkLinesWithVariables($variables, $lines) === false) {
            $this->toolBox()->i18nLog()->warning('not-equal-variables');
            $asiento->delete(); // Borramos todo el asiento, incluidas las líneas que se hubieran generado correctamente
            return $asiento; // Devolvemos el asiento vacío y no continua creando sus líneas
        }
        
        $saldoDebe = 0.0;
        $saldoHaber = 0.0;
        
        // Recorremos todas las líneas/partidas del asiento predefinido para crearlas en el asiento que estamos creando
        foreach ($lines as $line) {
            $newLine = $asiento->getNewLine(); // Crea la línea pero con los campos vacíos
            
            
            $mensajeError = '';
            
            // Creamos la subcuenta sustituyendo las variables que tuviera por su valor
            $codsubcuenta = $line->codsubcuenta;
            if ($this->varLineReplace($saldoDebe, $saldoHaber, $form, $variables, $codsubcuenta) === false) {
                $this->toolBox()->i18nLog()->warning('En la subucuenta ' . $line->codsubcuenta .' hay variables que todavía no se han creado en la pestaña Variables.');
                $asiento->delete(); // Borramos todo el asiento, incluidas las líneas que se hubieran generado correctamente
                return $asiento; // Devolvemos el asiento vacío y no continua creandole líneas
            }
            
            // Creamos el debe sustituyendo las variables que tuviera por su valor
            $debe = $line->debe;
            if ($this->varLineReplace($saldoDebe, $saldoHaber, $form, $variables, $debe) === false) {
                $this->toolBox()->i18nLog()->warning('En el DEBE(' . $line->debe . ') de la subucuenta ' . $line->codsubcuenta .' hay variables que todavía no se han creado en la pestaña Variables.');
                $asiento->delete(); // Borramos todo el asiento, incluidas las líneas que se hubieran generado correctamente
                return $asiento; // Devolvemos el asiento vacío y no continua creandole líneas
            }
            $debe = floatval($debe);
            
            // Creamos el haber sustituyendo las variables que tuviera por su valor
            $haber = $line->haber;
            if ($this->varLineReplace($saldoDebe, $saldoHaber, $form, $variables, $haber) === false) {
                $this->toolBox()->i18nLog()->warning('En el HABER(' . $line->haber . ') de la subucuenta ' . $line->codsubcuenta .' hay variables que todavía no se han creado en la pestaña Variables.');
                $asiento->delete(); // Borramos todo el asiento, incluidas las líneas que se hubieran generado correctamente
                return $asiento; // Devolvemos el asiento vacío y no continua creandole líneas
            }
            $haber = floatval($haber);
            
            $saldoDebe += $debe;
            $saldoHaber += $haber;
            
            // Una vez calculados bien los valores con variables, los asignamos a la línea
            $subcuenta = $newLine->getSubcuenta($codsubcuenta);
            $newLine->setAccount($subcuenta);
            $newLine->concepto = $line->concepto;
            $newLine->debe = $debe;
            $newLine->haber = $haber;
            
            if (false === $newLine->save()) {
                $this->toolBox()->i18nLog()->warning('No se pudo grabar la línea del asiento con la subucuenta ' . $codsubcuenta);
                $asiento->delete(); // Borramos todo el asiento, incluidas las líneas que se hubieran generado correctamente
                return $asiento; // Devolvemos el asiento vacío y no continua creandole líneas
            }
        }

        $asiento->importe = $saldoDebe; // Asignamos al concepto el campo concepto de la cabecera del asiento predefinido
        
        if (false === $asiento->save()) {
            $this->toolBox()->i18nLog()->warning('No se pudo actualizar el campo importe del asiento');
            $asiento->delete(); // Borramos todo el asiento, incluidas las líneas que se hubieran generado correctamente
            return $asiento; // Devolvemos el asiento vacío y no continua creandole líneas
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
    
    protected function varLineReplace(float $saldoDebe, float $saldoHaber, array $form, array $variables, string &$dondeQuitamosVariables): bool
    {
        $aDevolver = true;
        
        foreach($variables as $var) {
            $dondeQuitamosVariables = str_replace($var->codigo, $form['var_'.$var->codigo], $dondeQuitamosVariables);
        }

        $resultado = $saldoDebe - $saldoHaber;
        settype($resultado, "string"); // Convertimos $resultado en un string
        
        $dondeQuitamosVariables = str_replace('Z', $resultado, $dondeQuitamosVariables); // Si es una subcuenta, nunca va a a reemplazar Z, porque ya controlamos en la pestaña Z que no se use si es una subcuenta
        
        $hayVariablesTodavia = preg_replace("/[^A-Z\s]/", "", $dondeQuitamosVariables); // Dejamos sólo las variables A-Z, si las hubiera
        if (strlen($hayVariablesTodavia) > 0) {
            $aDevolver = false; // Hay variables todavía ... no se crearon todas las variables para el asiento predefinido en pestaña Variables 
        }

        return $aDevolver;
    }
    
}
