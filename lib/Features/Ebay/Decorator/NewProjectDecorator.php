<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 22/12/2016
 * Time: 11:53
 */

namespace Features\Ebay\Decorator;

use Features\Ebay\Utils\Routes ;
use Features\Ebay;


class NewProjectDecorator extends \AbstractDecorator
{


    /**
     * @var \PHPTALWithAppend
     */
    protected $template ;

    public function decorate()
    {
        $this->template->append('footer_js', Routes::staticSrc('js/ebay-upload.js') );
        $this->template->append('footer_js', Routes::staticSrc('js/libs/datepicker.min.js') );

        $this->template->append('css_resources', Routes::staticSrc('css/datepicker.min.css') );

        $path = Ebay::getTemplatesPath() ;
        $this->template->additional_input_params_base_path = $path . '/Html/' ;
    }

}