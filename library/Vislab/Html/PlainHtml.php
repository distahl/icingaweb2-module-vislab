<?php
namespace Icinga\Module\Vislab\Html;

use ipl\Html\ValidHtml;

class PlainHtml implements ValidHtml
{
    protected $content ="";

    public function setContent($content)
    {
        $this->content = $content;
        return $this;
    }
    public function render()
    {
        return $this->content;
    }
}