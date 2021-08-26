<?php
namespace FacturaScripts\Plugins\AsientosPredefinidos\Model;

class AsientoPredefinidoLinea extends \FacturaScripts\Core\Model\Base\ModelClass
{
    use \FacturaScripts\Core\Model\Base\ModelTrait;

    public $idasientopredefinidolinea;
    public $idasientopredefinido;
    public $codsubcuenta;
    public $idsubcuenta;
    public $codcontrapartida;
    public $idcontrapartida;
    public $concepto;
    public $debe;
    public $haber;
    public $orden;

    public function clear() {
        parent::clear();
        
        $this->debe = 0;
        $this->haber = 0;
        $this->orden = 0;
    }

    public static function primaryColumn() {
        return "idasientopredefinidolinea";
    }

    public static function tableName() {
        return "asientospredefinidoslineas";
    }
    
    public function test() {
        if ($this->comprobarSubcuenta() === false){
            return false;
        }
        
        if ($this->comprobarContrapartida() === false){
            return false;
        }
        
        $this->comprobarOrden();
        
        $this->evitarInyeccionSQL();
        return parent::test();
    }
	
    private function evitarInyeccionSQL()
    {
        $utils = $this->toolBox()->utils();
        $this->concepto = $utils->noHtml($this->concepto);
    }
    
    private function comprobarSubcuenta() : bool
    {
        $aDevolver = true;
        
        // Comprobamos si la cuenta existe
        $sql = ' SELECT subcuentas.idsubcuenta '
             . ' FROM subcuentas '
             . ' WHERE subcuentas.codsubcuenta = "' . $this->codsubcuenta . '" '
             . ' ORDER BY subcuentas.codsubcuenta '
             ;

        $registros = self::$dataBase->select($sql); // Para entender su funcionamiento visitar ... https://facturascripts.com/publicaciones/acceso-a-la-base-de-datos-818

        foreach ($registros as $fila) {
            if (empty($fila['idsubcuenta'])) {
                $aDevolver = false;
                $this->toolBox()->i18nLog()->error( "La subcuenta no existe." );
            } else {
                $this->idsubcuenta = $fila['idsubcuenta'];
            }
        }
        
        return $aDevolver;
    }
    
    private function comprobarContrapartida() : bool
    {
        $aDevolver = true;
        
        // Comprobamos si la cuenta existe
        $sql = ' SELECT subcuentas.idsubcuenta '
             . ' FROM subcuentas '
             . ' WHERE subcuentas.codsubcuenta = "' . $this->codcontrapartida . '" '
             . ' ORDER BY subcuentas.codsubcuenta '
             ;

        $registros = self::$dataBase->select($sql); // Para entender su funcionamiento visitar ... https://facturascripts.com/publicaciones/acceso-a-la-base-de-datos-818

        foreach ($registros as $fila) {
            if (empty($fila['idsubcuenta'])) {
                $aDevolver = false;
                $this->toolBox()->i18nLog()->error( "La subcuenta no existe." );
            } else {
                $this->idcontrapartida = $fila['idsubcuenta'];
            }
        }
        
        return $aDevolver;
    }
    
    private function comprobarOrden()
    {
        if (empty($this->orden) or $this->orden === 0) {
            // Comprobamos si la cuenta existe
            $sql = ' SELECT MAX(asientospredefinidoslineas.orden) AS orden '
                 . ' FROM asientospredefinidoslineas '
                 . ' WHERE asientospredefinidoslineas.idasientopredefinido = ' . $this->idasientopredefinido
                 . ' ORDER BY asientospredefinidoslineas.idasientopredefinido '
                 .        ' , asientospredefinidoslineas.orden '
                 ;

            $registros = self::$dataBase->select($sql); // Para entender su funcionamiento visitar ... https://facturascripts.com/publicaciones/acceso-a-la-base-de-datos-818

            foreach ($registros as $fila) {
                if (empty($fila['orden'])) {
                    $this->orden = 1;
                } else {
                    $this->orden = ($fila['orden'] + 5);
                }
            }

        }
    }
    
    
}
