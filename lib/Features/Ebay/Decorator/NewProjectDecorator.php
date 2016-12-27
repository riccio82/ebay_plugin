<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 22/12/2016
 * Time: 11:53
 */

namespace Features\Ebay\Decorator;


use Features\Ebay;

class NewProjectDecorator extends \AbstractDecorator
{

    public function decorate()
    {
        $path = Ebay::getTemplatesPath() ;
        $this->template->additional_input_params_base_path = $path . '/Html/' ;
    }

}