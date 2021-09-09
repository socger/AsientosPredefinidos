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
        $asiento->idasientopre = $this->id;
        
        if (false === $asiento->save()) {
            $this->toolBox()->i18nLog()->warning('no-can-create-accounting-entry');
            return $asiento; // Devolvemos el asiento incompleto, vacío.
        }

        // Traemos en arrays las líneas y variables creadas del asiento predefinido
        $variables = $this->getVariables();
        $lines = $this->getLines();

        // Comprobamos las variables y líneas
        if (false === $this->checkVariables($form, $variables, $lines)) {
            $asiento->delete(); // Borramos el asiento, incluidas las líneas que se hubieran generado correctamente
            return $asiento; // Devolvemos el asiento vacío y no continúa creando sus líneas
        }

        $saldoDebe = 0.0;
        $saldoHaber = 0.0;

        // Recorremos todas las líneas/partidas del asiento predefinido para crearlas en el asiento que estamos creando
        foreach ($lines as $line) {
            $newLine = $asiento->getNewLine(); // Crea la partida, con los campos vacíos
            $newLine->concepto = $line->concepto;
            $newLine->debe = (float)$this->varLineReplace($line->debe, $variables, $form, $saldoDebe, $saldoHaber);
            $newLine->haber = (float)$this->varLineReplace($line->haber, $variables, $form, $saldoDebe, $saldoHaber);

            // Reemplazamos/Calculamos la subcuenta del asiento y su id
            $newLine->codsubcuenta = $this->varLineReplace($line->codsubcuenta, $variables, $form, $saldoDebe, $saldoHaber, 'S', $asiento->getExercise());
            $newLine->setAccount($newLine->getSubcuenta($newLine->codsubcuenta)); // setAccount, asigna subcuenta y idsubcuenta a la partida/línea

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
     * Calcula/añade en &$array las variables que encuentre en $toCheck
     *
     * @param string $toCheck
     * @param array $array
     */
    protected function addToVariables(string $toCheck, array &$array)
    {
        // Dejamos sólo los caracteres aceptados ... letras en mayúsculas (A-Z).
        $caracteresAceptados = preg_replace("/[^A-Z\s]/", "", $toCheck);

        for ($i = 0; $i < strlen($caracteresAceptados); $i++) {
            if ($caracteresAceptados[$i] <> 'Z' && true !== in_array($caracteresAceptados[$i], $array)){ 
                // Z->NO ES VARIABLE a usar en pestaña Generar(Asientos Predef)
                // AUNQUE se usará para poner saldo descuadre asiento
                $array[] = $caracteresAceptados[$i];
            }
        }
    }

    /**
     * Comprobamos que todas las variables tengan valor y que ctdad.variables pestaña Lineas = ctdad.variables pestaña Variables del Asiento Predefinido
     *
     * @param array $form
     * @param array $variables
     * @param array $lines
     *
     * @return bool
     */
    protected function checkVariables(array $form, array $variables, array $lines): bool
    {
        // ¿Todas las variables tienen valor?
        $mensaje = '';
        foreach ($form as $clave => $valor) {
            $clave = str_replace('var_', "", $clave);
            if (empty($valor) || $valor === '0') {
                $mensaje .= empty($mensaje) ? $clave : ', ' . $clave;
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
            // Z no es variable creada desde pestaña Generar de Asientos Predefinidos
            // AUNQUE se usa para poner el valor del descuadre del asiento en pestaña Lineas
            if ($variable->codigo === 'Z') {
                continue;
            }

            // Para el resto de variables que no son Z
            if (true !== in_array($variable->codigo, $variablesEnVariables)) {
                $variablesEnVariables[] = $variable->codigo;
            }
        }

        // Es ctdad variables pestaña Lineas = ctdad variables pestaña Lineas?
        if (count($variablesEnLineas) !== count($variablesEnVariables)) {
            $this->toolBox()->i18nLog()->warning('not-equal-variables');
            return false;
        }
        
        return true;
    }

    /**
     * Reemplazamos variables de A-Y, por valor almacenado en $form
     * Reemplazamos variable Z por el descuadre del asiento
     * Si es subcuenta, sustituimos el punto por tantos 0 hasta longitud subcuenta, según ejercicio
     * Si es una fórmula, calculamos
     *
     * @param string $value
     * @param array $variables
     * @param array $form
     * @param float $saldoDebe
     * @param float $saldoHaber
     * @param string $tipo
     * @param ?Ejercicio $ejercicio
     *
     * @return string
     */
    protected function varLineReplace(string $value, array $variables, array $form, float $saldoDebe, float $saldoHaber, string $tipo = 'N', ?Ejercicio $ejercicio = null): string
    {
        // Reemplazamos variables de A-Y, por valor
        foreach ($variables as $var) {
            $value = str_replace($var->codigo, $form['var_' . $var->codigo], $value);
        }

        // Si es subcuenta, sustituimos el punto por tantos 0 hasta longitud subcuenta, según ejercicio
        if ($tipo === 'S') {
            if (strpos($value, '.') === false) {
                // No hay punto y es subcuenta. No hay que cálcular fórmulas, ni sustituir variable Z
                return trim($value);
            }

            // Hay punto, sustituimos por ceros
            $parts = explode('.', trim($value));
            if (count($parts) === 2) {
                // Solamente hay un punto
                return str_pad($parts[0]
                        , $ejercicio->longsubcuenta - strlen($parts[1])
                        , '0'
                        , STR_PAD_RIGHT
                    ) . $parts[1];
            }

            // Hay más de un punto
            return trim($value);
        }

        // Reemplazamos variable Z por el descuadre del asiento
        $descuadre = $saldoDebe - $saldoHaber;
        $value = str_replace('Z', (string)$descuadre, $value);

        // Si es una fórmula, calculamos
        foreach (['+', '-', '/', '*'] as $operator) {
            if (false !== strpos($value, $operator)) {
                $expressionLanguage = new ExpressionLanguage();
                return (string)$expressionLanguage->evaluate($value);
            }
        }

        return $value;
    }
}
