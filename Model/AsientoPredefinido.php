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
use FacturaScripts\Dinamic\Model\Ejercicio;
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

    /**
     * @param array $form
     *
     * @return Asiento
     */
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

        // Comprobamos las variables y líneas
        if (false === $this->checkVariables($form, $variables, $lines)) {
            $asiento->delete(); // Borramos todo el asiento, incluidas las líneas que se hubieran generado correctamente
            return $asiento; // Devolvemos el asiento vacío y no continua creando sus líneas
        }

        $saldoDebe = 0.0;
        $saldoHaber = 0.0;

        // Recorremos todas las líneas/partidas del asiento predefinido para crearlas en el asiento que estamos creando
        foreach ($lines as $line) {
            $newLine = $asiento->getNewLine(); // Crea la línea pero con los campos vacíos

            $newLine->concepto = $line->concepto;
            $newLine->debe = (float)$this->varLineReplace($asiento->codejercicio, 'N', $line->debe, $variables, $form, $saldoDebe, $saldoHaber);
            $newLine->haber = (float)$this->varLineReplace($asiento->codejercicio, 'N', $line->haber, $variables, $form, $saldoDebe, $saldoHaber);
            
            // Reemplazamos/Calculamos la subcuenta del asiento y su id
            $newLine->codsubcuenta = $this->varLineReplace($asiento->codejercicio, 'S', $line->codsubcuenta, $variables, $form, $saldoDebe, $saldoHaber);
            $newLine->setAccount( $newLine->getSubcuenta($newLine->codsubcuenta) ); // setAccount, asigna subcuenta y idsubcuenta a la partida/línea

            // Guardamos la línea
            if (false === $newLine->save()) {
                $this->toolBox()->i18nLog()->warning('record-save-error');
                $asiento->delete();
                return $asiento;
            }

            // Recalculamos saldo de asiento
            $saldoDebe += $newLine->debe;
            $saldoHaber += $newLine->haber;
        }

        // Rellenamos el campo Importe de cabecera del asiento
        $asiento->importe = $saldoDebe; 
        if (false === $asiento->save()) {
            $this->toolBox()->i18nLog()->warning('record-save-error');
            $asiento->delete();
        }

        return $asiento;
    }

    /**
     * Devuelve un array con las líneas del asiento predefinido.
     *
     * @return AsientoPredefinidoLinea[]
     */
    public function getLines(): array
    {
        $line = new AsientoPredefinidoLinea();
        $where = [new DataBaseWhere("idasientopre", $this->id)];
        return $line->all($where);
    }

    /**
     * Devuelve un array con las variables del asiento predefinido.
     *
     * @return AsientoPredefinidoVariable[]
     */
    public function getVariables(): array
    {
        $variable = new AsientoPredefinidoVariable();
        $where = [new DataBaseWhere("idasientopre", $this->id)];
        return $variable->all($where);
    }

    /**
     * @return string
     */
    public static function primaryColumn()
    {
        return "id";
    }

    /**
     * @return string
     */
    public static function tableName()
    {
        return "asientospre";
    }

    /**
     * @return bool
     */
    public function test()
    {
        $utils = $this->toolBox()->utils();
        $this->concepto = $utils->noHtml($this->concepto);
        $this->descripcion = $utils->noHtml($this->descripcion);

        return parent::test();
    }

    /**
     * @param string $type
     * @param string $list
     *
     * @return string
     */
    public function url(string $type = 'auto', string $list = 'ListAsiento?activetab=List'): string
    {
        return parent::url($type, $list);
    }

    /**
     * @param string $toCheck
     * @param array $list
     * 
     * Calcula/añade en &$array las variables que encuentre en $toCheck
     */
    protected function addToVariables(string $toCheck, array &$array)
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

    /**
     * @param array $form
     * @param array $variables
     * @param array $lines
     *
     * @return bool
     * Comprobamos que todas las variables tengan valor y que ctdad.variables pestaña Lineas = ctdad.variables pestaña Variables del Asiento Predefinido
     */
    protected function checkVariables(array $form, array $variables, array $lines): bool
    {
        // ¿Todas las variables tienen valor?
        $mensaje = '';
        foreach ($form as $clave => $valor) {
            $clave = str_replace('var_', "", $clave);

            if (empty($valor) or $valor === '0') {
                $mensaje .= ($mensaje === '') ? $clave : ', ' . $clave;
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


    /**
     * @param string $codejercicio
     * @param string $tipo
     * @param string $dondeQuitamosVariables
     * @param array $variables
     * @param array $form
     * @param float $saldoDebe
     * @param float $saldoHaber

     * @return string
     * 
     * Reemplazamos variables de A-Y, por valor
     * Reemplazamos variable Z por saldo descuadre asiento
     * Si es subcuenta, sustituimos el punto por tantos 0 hasta longitud subcuenta, según ejercicio
     * Si es una fórmula, calculamos
     */
    protected function varLineReplace(string $codejercicio, string $tipo, string $dondeQuitamosVariables, array $variables, array $form, float $saldoDebe, float $saldoHaber): string
    {
        // Reemplazamos variables de A-Y, por valor
        foreach ($variables as $var) {
            $dondeQuitamosVariables = str_replace($var->codigo, $form['var_' . $var->codigo], $dondeQuitamosVariables);
        }

        // Reemplazamos variable Z por saldo descuadre asiento
        $resultado = (string)($saldoDebe - $saldoHaber);

        $dondeQuitamosVariables = str_replace('Z', $resultado, $dondeQuitamosVariables); // Si es una subcuenta, nunca va a a reemplazar Z, porque ya controlamos en la pestaña Z que no se use si es una subcuenta
        
        // Si es subcuenta, sustituimos el punto por tantos 0 hasta longitud subcuenta, según ejercicio
        if ($tipo === 'S'){
            if (\strpos($dondeQuitamosVariables, '.') === false) {
                // No hay punto
                $dondeQuitamosVariables = \trim($dondeQuitamosVariables);
            } else {
                // Hay punto
                $parts = \explode('.', \trim($dondeQuitamosVariables));
                if (\count($parts) === 2) {
                    // Sólo hay un punto
                    $ejercicio = new Ejercicio();
                    if ($ejercicio->loadFromCode($codejercicio)) {
                        $dondeQuitamosVariables = \str_pad( $parts[0]
                                                          , $ejercicio->longsubcuenta - \strlen($parts[1])
                                                          , '0'
                                                          , \STR_PAD_RIGHT
                                                          ) . $parts[1];
                    } 
                } else {
                    // Hay más de un punto
                    $dondeQuitamosVariables = \trim($dondeQuitamosVariables);
                }
            }
        }
        
        // Si es una fórmula, calculamos
        foreach (['+', '-', '/', '*'] as $operator) {
            if (false !== strpos($dondeQuitamosVariables, $operator)) {
                $expressionLanguage = new ExpressionLanguage();
                return (string)$expressionLanguage->evaluate($dondeQuitamosVariables);
            }
        }
        
        return $dondeQuitamosVariables;
    }

}
