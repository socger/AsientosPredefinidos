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
    public $descripcion;

    /**
     * @var int
     */
    public $id;

    public function generate(string $date, int $idempresa): Asiento
    {
        $asiento = new Asiento();
        $asiento->idempresa = $idempresa;
        $asiento->setDate($date);
        $asiento->concepto = $this->descripcion;
        if (false === $asiento->save()) {
            return $asiento;
        }

        foreach ($this->getLines() as $line) {
            $newLine = $asiento->getNewLine();
            $newLine->codsubcuenta = $line->codsubcuenta;
            $newLine->codcontrapartida = $line->codcontrapartida;
            $newLine->concepto = $line->concepto;
            $newLine->debe = $line->debe;
            $newLine->haber = $line->haber;
            if (false === $newLine->save()) {
                $asiento->delete(); // Si no se graba, pues borra la cabecera del asiento
                return $asiento;
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
        $this->descripcion = $this->toolBox()->utils()->noHtml($this->descripcion);
        return parent::test();
    }

    public function url(string $type = 'auto', string $list = 'ListAsiento?activetab=List'): string
    {
        return parent::url($type, $list);
    }

}
