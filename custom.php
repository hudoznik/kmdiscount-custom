<?php

/**
 * @copyright   Copyright (C) 2013. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

if (!class_exists('KMDiscountPlugin')) {
	require(JPATH_ROOT . DS . 'administrator' . DS . 'components' . DS . 'com_ksenmart' . DS . 'classes' . DS . 'kmdiscountplugin.php');
}

class plgKMDiscountCustom extends KMDiscountPlugin
{

	var $_params = array(
		'value' => 0,
		'type'  => 1,
		'items'  => 1,
		'custom' => 1,
	);

	function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
	}

	function onDisplayParamsForm($name = '', $params = null)
	{
		if ($name != $this->_name)
			return;
		if (empty($params)) $params = $this->_params;
		$currency_code = $this->getDefaultCurrencyCode();
		$wa   = Factory::getApplication()->getDocument()->getWebAssetManager();
		$wa->getRegistry()->addRegistryFile('media/system/joomla.asset.json');
		$wa->useStyle('switcher');
		$html = '';
		$html .= '<div class="set">';
		$html .= '	<h3 class="headname">Как считать скидку</h3>';
		$html .= '	<div class="row">';
		$html .= '		<div class="switcher has-success">
			<input type="radio" id="jform_items0" name="jform[params][items]" value="0" ' . ($params['items'] == 0 ? 'checked="" class="active "' : 'class="valid form-control-success" aria-invalid="false"') . '>
			<label for="jform_items0">Делить по позициям</label>
			<input type="radio" id="jform_items1" name="jform[params][items]" value="1" ' . ($params['items'] == 1 ? 'checked="" class="active "' : 'class="valid form-control-success" aria-invalid="false"') . '>
			<label for="jform_items1">Вычитать из общей стоимости заказа</label>
			<span class="toggle-outside"><span class="toggle-inside"></span></span>
			<input type="hidden" id="jform_custom" name="jform[params][custom]" value="1">
		</div>';
		$html .= '	<div class="row">';
		$html .= '		<label class="inputname">Значение скидки в</label>';
		$html .= '		<select class="sel" name="jform[params][type]">';
		$html .= '			<option value="0" ' . ($params['type'] == 0 ? 'selected' : '') . '>%</option>';
		$html .= '			<option value="1" ' . ($params['type'] == 1 ? 'selected' : '') . '>' . $currency_code . '</option>';
		$html .= '		</select>';
		$html .= '	</div>';
		$html .= '	</div>';
		$html .= '</div>';
		return $html;
	}

	function onSetCartDiscount($cart = null, $discount_id = null)
	{
		// ПРОСЧЕТ СКИДКИ В КОРЗИНЕ НА КЛИЕНТЕ
		return false;
	}

	function onSetProductDiscount($prd = null, $discount_id = null)
	{
	}

	function onSetOrderDiscount(&$order = null, $discount_id = null, $params = null)
	{
		

		if (empty($order)) return false;
		if (empty($discount_id)) return false;
		if (empty($params)) return false;
		$db = JFactory::getDBO();

		$_discounts = json_decode($order->discounts);
		$discount = KSMPrice::getDiscount($discount_id);
		if (empty($discount)) return false;
		//Если вычитать из общей стоимости заказа
		if ($params["items"] == 1) {

			if ($params["type"] == 0) {
				// Проценты
				$params["sum"] = round($order->costs["cost"] / 100 * $params["value"]);
			} else {
				// Сумма
				$params["sum"] = $params["cost"];
			}
			$_discounts->{$discount_id}->sum = $params["sum"];
			$order->costs["discount_cost"] += $params["sum"];
			$order->discounts = json_encode($_discounts);
			return true;
		}
		// Если вичитать из стоимости позиций
		if ($params["type"] == 1 && $params["items"] == 0) {
			// Если сумма, то считаем сколько за одну позицию
			$_discount = $_discounts->$discount_id;
			$count_prod = 0;
			foreach ($order->items as &$item) {
				$return = $this->onCheckDiscountCategories($discount_id, $item->product_id);
				if (!$return) continue;
				$return = $this->onCheckDiscountManufacturers($discount_id, $item->product_id);
				if (!$return) continue;
				$return = $this->onCheckDiscountProducts($discount_id, $item->product_id);
				if (!$return) continue;
				if ($item->price) $count_prod += $item->count;
			}
			$params["value"] = $params["cost"] / $count_prod;
		}
		$discount->params["value"] = $params["value"];
		$params["sum"] = 0;


		foreach ($order->items as &$item) {
			$discount->discount_value = 0;
			if (!isset($item->discounts)) $item->discounts = array();
			$return = $this->onCheckDiscountCategories($discount_id, $item->product_id);

			if (!$return) continue;
			$return = $this->onCheckDiscountManufacturers($discount_id, $item->product_id);
			if (!$return) continue;
			$return = $this->onCheckDiscountProducts($discount_id, $item->product_id);
			if (!$return) continue;

			$item->discounts[$discount_id] = $this->calculateItemDiscount($item, $discount, $discount_set_value, $params);
			$params["sum"] += round($item->discounts[$discount_id]->discount_value);
		}
		
		if (is_string($order->discounts)) $order->discounts = json_decode($order->discounts);
		$order->discounts->{$discount_id} = $params;
		$order->discounts = json_encode($order->discounts);

		return true;
	}

	function onGetDiscountContent($discount_id = null)
	{
		return;
	}
}
