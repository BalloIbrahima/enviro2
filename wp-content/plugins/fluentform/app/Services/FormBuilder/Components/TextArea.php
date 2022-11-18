<?php

namespace FluentForm\App\Services\FormBuilder\Components;

use FluentForm\App\Helpers\Helper;

class TextArea extends BaseComponent
{
    /**
     * Compile and echo the html element
     *
     * @param array     $data [element data]
     * @param \stdClass $form [Form Object]
     *
     * @return void
     */
    public function compile($data, $form)
    {
        $elementName = $data['element'];
        $data = apply_filters('fluentform_rendering_field_data_' . $elementName, $data, $form);

        $textareaValue = $this->extractValueFromAttributes($data);

        $data['attributes']['class'] = trim('ff-el-form-control ' . $data['attributes']['class']);
        $data['attributes']['id'] = $this->makeElementId($data, $form);

        if ($tabIndex = Helper::getNextTabIndex()) {
            $data['attributes']['tabindex'] = $tabIndex;
        }

        $elMarkup = '<textarea %s>%s</textarea>';

        $atts = $this->buildAttributes($data['attributes']);

        $elMarkup = sprintf(
            $elMarkup,
            $atts,
            esc_attr($textareaValue)
        ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $atts is escaped before being passed in.

        $html = $this->buildElementMarkup($elMarkup, $data, $form);

        $this->printContent('fluentform_rendering_field_html_' . $elementName, $html, $data, $form);
    }
}
