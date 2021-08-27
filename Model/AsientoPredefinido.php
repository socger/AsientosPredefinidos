<?php

namespace FacturaScripts\Plugins\AsientosPredefinidos\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\Asiento;

class AsientoPredefinido extends \FacturaScripts\Core\Model\Base\ModelClass {

    use \FacturaScripts\Core\Model\Base\ModelTrait;

    /**
     * 
     * @var string
     */
    public $descripcion;

    /**
     * 
     * @var int
     */
    public $id;

    public function clear() {
        parent::clear();
    }

    public function getLines() {
        $line = new AsientoPredefinidoLinea();
        $where = [new DataBaseWhere("idasientopre", $this->id)];

        return $line->all($where);
    }

    public function generate(string $date, int $idempresa): Asiento {
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

            if (false === $newLine->save()) {
                $asiento->delete(); // Si no se graba, pues borra la cabecera del asiento
                return $asiento;
            }
        }

        return $asiento;
    }

    public static function primaryColumn() {
        return "id";
    }

    public static function tableName() {
        return "asientospre";
    }

    public function test() {
        $this->descripcion = $this->toolBox()->utils()->noHtml($this->descripcion);
        return parent::test();
    }

    public function url(string $type = 'auto', string $list = 'ListAsiento?activetab=List'): string {
        return parent::url($type, $list);
    }

}
