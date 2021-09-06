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
// use FacturaScripts\Dinamic\Model\Subcuenta;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;


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
        // Creamos modelo asiento y rellenamos sus campos
        $asiento = new Asiento(); 
        $asiento->idempresa = $form["idempresa"];
        $asiento->setDate($form["fecha"]);
        $asiento->concepto = $this->concepto;
        
        if (false === $asiento->save()) {
            $this->toolBox()->i18nLog()->warning('no-can-create-accounting-entry');
            return $asiento; // Devolvemos el asiento incompleto, vacío.
        }

        // Traemos en arrays las líneas y variables creadas del asiento predefinido
        $variables = $this->getVariables();
        $lines = $this->getLines();

        // Comprobamos que ctdad variables pestaña Lineas = ctdad variables pestaña Variables
        if ($this->checkVariables($form, $variables, $lines) === false) {
            $asiento->delete(); // Borramos todo el asiento, incluidas las líneas que se hubieran generado correctamente
            return $asiento; // Devolvemos el asiento vacío y no continua creando sus líneas
        }
        
        $saldoDebe = 0.0;
        $saldoHaber = 0.0;
        
        // Recorremos todas las líneas/partidas del asiento predefinido para crearlas en el asiento que estamos creando
        foreach ($lines as $line) {
            $newLine = $asiento->getNewLine(); // Crea la línea pero con los campos vacíos
            
            // Creamos la subcuenta sustituyendo las variables que tuviera por su valor
            $newLine->codsubcuenta = $this->varLineReplace($line->codsubcuenta, $variables, $form, $saldoDebe, $saldoHaber);
            $newLine->debe = (float) $this->varLineReplace($line->debe, $variables, $form, $saldoDebe, $saldoHaber);
            $newLine->haber = (float) $this->varLineReplace($line->haber, $variables, $form, $saldoDebe, $saldoHaber);
            
            $subcuenta = $newLine->getSubcuenta($newLine->codsubcuenta);
            $newLine->setAccount($subcuenta);
            $newLine->concepto = $line->concepto;
            
            if (false === $newLine->save()) {
                $this->toolBox()->i18nLog()->warning('No se pudo grabar la línea del asiento con la subucuenta ' . $codsubcuenta);
                $asiento->delete(); // Borramos todo el asiento, incluidas las líneas que se hubieran generado correctamente
                return $asiento; // Devolvemos el asiento vacío y no continua creandole líneas
            }

            // Recalculamos saldo de asiento
            $saldoDebe += $newLine->debe;
            $saldoHaber += $newLine->haber;
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
    
    protected function checkVariables(array $form, array $variables, array $lines): bool
    {
        // ¿Todas las variables tienen valor?
        $mensaje = '';
        foreach($form as $clave => $valor) {
            $clave = str_replace('var_', "", $clave);
            
            if (empty($valor) or $valor === '0') {
                $mensaje .= ($mensaje === '') ? $clave : ', '.$clave;
            }
        }

        if ($mensaje <> '') {
            $mensaje = 'No introdujo valor a las variables ' . $mensaje;
            $this->toolBox()->i18nLog()->warning($mensaje);
            return false;
        }
        
        // ¿Es ctdad variables pestaña Lineas = ctdad variables pestaña Lineas?
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

        // Es ctdad variables pestaña Lineas = ctdad variables pestaña Lineas
        //$aDevolver = count($variablesEnLineas) === count($variablesEnVariables) ? $this->toolBox()->i18nLog()->warning('not-equal-variables') : null;
        $aDevolver = count($variablesEnLineas) === count($variablesEnVariables);
        
        if ($aDevolver === false) {
            $this->toolBox()->i18nLog()->warning('not-equal-variables');
        }
        
        return $aDevolver;
    }
    
    protected function varLineReplace(string $dondeQuitamosVariables, array $variables, array $form, float $saldoDebe, float $saldoHaber): string
    {
        // Reemplazamos variables de A-Y
        foreach($variables as $var) {
            $dondeQuitamosVariables = str_replace($var->codigo, $form['var_'.$var->codigo], $dondeQuitamosVariables);
        }

        // Reemplazamos variable Z
        $resultado = $saldoDebe - $saldoHaber;
        settype($resultado, "string"); // Convertimos $resultado en un string
        
        $dondeQuitamosVariables = str_replace('Z', $resultado, $dondeQuitamosVariables); // Si es una subcuenta, nunca va a a reemplazar Z, porque ya controlamos en la pestaña Z que no se use si es una subcuenta
        
        foreach(['+', '-', '/', '*'] as $operator) {
            if (false !== strpos($dondeQuitamosVariables, $operator) ) {
                $expressionLanguage = new ExpressionLanguage();
                return (string) $expressionLanguage->evaluate($dondeQuitamosVariables);
            }
        }
        
        return $dondeQuitamosVariables;
        
    }
    
}
