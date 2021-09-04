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
    
    protected function addToVariables(string $toCheck, &$array)
    {
        // Dejamos sólo los caracteres aceptados ... letras en mayúsculas (A-Z).
        $caracteresAceptados = preg_replace("/[^A-Z\s]/", "", $toCheck);

        for ($i = 0; $i < strlen($caracteresAceptados); $i++) {
            if ($caracteresAceptados[$i] <> 'Z') { // La Z ... NO ES UNA VARIABLE a crear su valor en pestaña Generar de Asientos Predefinidos, AUNQUE se usará para poner el valor del descuadre del asiento
                $existeEnArray = false;
                foreach ($array as &$valor) {
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
            if ($variable['codigo'] <> 'Z') { // La Z ... NO ES UNA VARIABLE a crear su valor en pestaña Generar de Asientos Predefinidos, AUNQUE se usará para poner el valor del descuadre del asiento
                $existeEnArray = false;
                foreach ($variablesEnVariables as &$valor) {
                    if ($valor === $variable['codigo']) {
                        $existeEnArray = true;
                        break;
                    }
                }
                
                if ($existeEnArray === false) {
                    $variablesEnVariables[] = $variable['codigo'];
                }
            }
        }

        // Comprobamos la cantidad de variables de pestaña Líneas con la cantidad de variables de pestaña Variables
        if ( count($variablesEnLineas) <> count($variablesEnVariables) ) {
            $aDevolver = false;
        }
        
        return $aDevolver;
    }

    public function generate(array $form): Asiento
    {
        $asiento = new Asiento(); // Creamos un modelo asiento

        $asiento->idempresa = $form["idempresa"]; // Rellenamos al asiento el idempresa del form
        $asiento->setDate($form["fecha"]); // Rellenamos al asiento el campo fecha
        $asiento->concepto = $this->concepto; // Asignamos al concepto el campo concepto de la cabecera del asiento predefinido
        
        
        if (false === $asiento->save()) {
            // No se pudo crear la cabecera del asiento, así que devolvemos el asiento incompleto
            return $asiento;
        }

        $variables = $this->getVariables(); // Traemos en un array todas las variables creadas para el asiento predefinido.
        $lines = $this->getLines();

        // Primero comprobaremos la cantidad de variables creadas en las líneas del asiento predefinido
        // Luego comprobaremos la cantidad de variables creadas (pestaña Generar del asiento predefinido)
        // Ambas cantidades debe de ser igual, además debemos de comprobar si todas las variables tienen su valor introducido para el asiento que vamos acrear
        if ($this->checkLinesWithVariables($variables, $lines) === false) {
            $asiento->delete(); // Borramos todo el asiento, incluidas las líneas que se hubieran generado correctamente
            return $asiento; // Devolvemos el asiento vacío y no continua creandole líneas
        }
        
        // Recorremos todas las líneas/partidas del asiento predefinido para crearlas en el asiento que estamos creando
        foreach ($lines as $line) {
            $newLine = $asiento->getNewLine(); // Crea la línea pero con los campos vacíos
            
            // Creamos la subcuenta sustituyendo las variables que tuviera por su valor
            $subcuenta = '';
            if ($this->varLineReplace($subcuenta, $line->codsubcuenta, $form, $variables) === false) {
                // Hay variables que todavía no se han creado para la subcuenta
                $asiento->delete(); // Borramos todo el asiento, incluidas las líneas que se hubieran generado correctamente
                return $asiento; // Devolvemos el asiento vacío y no continua creandole líneas
            }
            
            // Creamos el debe sustituyendo las variables que tuviera por su valor
            $debe = '';
            if ($this->varLineReplace($debe, $line->debe, $form, $variables) === false) {
                // Hay variables que todavía no se han creado para la subcuenta
                $asiento->delete(); // Borramos todo el asiento, incluidas las líneas que se hubieran generado correctamente
                return $asiento; // Devolvemos el asiento vacío y no continua creandole líneas
            }
            eval('$debe = ' . $debe . ';'); // Por si en $line->debe había una fórmula
            
            // Creamos el haber sustituyendo las variables que tuviera por su valor
            $haber = '';
            if ($this->varLineReplace($haber, $line->haber, $form, $variables) === false) {
                // Hay variables que todavía no se han creado para la subcuenta
                $asiento->delete(); // Borramos todo el asiento, incluidas las líneas que se hubieran generado correctamente
                return $asiento; // Devolvemos el asiento vacío y no continua creandole líneas
            }
            eval('$haber = ' . $haber . ';'); // Por si en $line->haber había una fórmula
            
            // Una vez calculados bien los valores con variables, los asignamos a la línea
            $newLine->codsubcuenta = $subcuenta;
            $newLine->concepto = $line->concepto;
            $newLine->debe = $debe;
            $newLine->haber = $haber;
            
            if (false === $newLine->save()) {
                // No se ha podido grabar la línea
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
    
    protected function varLineReplace(string &$sinVariable, string $conVariable, array $form, array $variables): bool
    {
        // Recorremos cada uno de los caracteres para ver si es variable o no
        for ($i = 0; $i < strlen($conVariable); $i++) {
            $caracter = preg_replace("/[^A-Z\s]/", "", $conVariable[$i]); // Sólo permitimos letras en mayúsculas
            if (strlen($caracter) <= 0) {
                // No es una variable, así que añadimos el caracter como parte de la subcuenta
                $sinVariable .= $caracter;
                continue;
            }

            // Es una variable, así que sustituimos el caracter por el valor de la variable que tenemos en $form
            // Pero antes tenemos que recorrer todas las variables para ver si está creada, si no lo estuviera sacar mensaje de ello
            $laVariableExiste = false;
            foreach ($variables as $variable) {
                if ($caracter === $variable['codigo']) {
                    $laVariableExiste = true;
                    break;
                }
            }

            if ($laVariableExiste === true) {
                $sinVariable .= $form['var_' . $caracter];
                continue;
            }
            
            return false; // Salimos sin terminar de sustituir las variables
        }

        return true;
    }

}
