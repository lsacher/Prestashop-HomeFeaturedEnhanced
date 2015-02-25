<?php
/*
* 
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
*/

/*
*  Home Featured Enhanced by Larry Sacherich 2014-04-20
*  Displays random, rollover images of selected featured products for your homepage.
*  Prestashop Version: 1.5.6.2
*  Inspired by: homefeatured rollover by Vivek Tripathi, homespecials by Nemo,
*               homefeatured2 by Mediacom87, homefeatured by Prestashop
*               
*  Features:
*  - Select items from any category or all categories or Home/Featured
*  - Includes 3 psuedo categories: Price Drop, New Products, & All Products
*  - New Products includes a "New" ribbon overlay
*  - Select any number of items to display
*  - Displays second image in a rollover effect (or a message if no image)
*  - CSS3 rollover efffects
*  - Select rollover effects: RotateX, RotateY, Crossfade, Zooming or None
*  - Items can be randomly selected
*  - Product templates are cached
* 
*/

if (!defined('_PS_VERSION_'))
	exit;

class HomeFeaturedEnhanced extends Module
{
	private $_html = '';
	private $_postErrors = array();

	function __construct()
	{
		$this->name = 'homefeaturedenhanced';
		$this->tab = 'front_office_features';
		$this->version = '1.0';
		$this->author = 'Larry Sacherich';
		$this->need_instance = 0;
		parent::__construct();
		$this->displayName = $this->l('Featured products on the homepage - Enhanced');
		$this->description = $this->l('Displays random, rollover images of selected featured products for your homepage.');
	}

	function install()
	{
		if (!parent::install() 
      || !Configuration::updateValue('HOME_FEATURED_NBR', 8) 
			|| !Configuration::updateValue('HOME_FEATURED_RANDOM', 1)
			|| !Configuration::updateValue('HOME_FEATURED_ROLLOVER', 'rotateX')
			|| !Configuration::updateValue('HOME_FEATURED_CATEGORY', Configuration::get(PS_HOME_CATEGORY))
			|| !$this->registerHook('header')
			|| !$this->registerHook('displayHome')
			|| !$this->registerHook('addproduct')
			|| !$this->registerHook('updateproduct')
			|| !$this->registerHook('deleteproduct')
		)
			return false;
		return true;
	}

	public function getContent()
	{
		$output = '<h2>'.$this->displayName.'</h2>';
		if (Tools::isSubmit('submitHomeFeaturedEnhanced'))
		{
			$nbr = (int)(Tools::getValue('nbr'));

			if (!$nbr OR $nbr == 0 OR !Validate::isInt($nbr))
				$errors[] = $this->l('An invalid number of products has been specified.');
			else
				Configuration::updateValue('HOME_FEATURED_NBR', (int)($nbr));

			$cat = Tools::getValue('cat');
			if (!$cat OR $cat == 0 OR !Validate::isFloat($cat))
				$errors[] = $this->l('An invalid category has been specified.');
			else
		   	Configuration::updateValue('HOME_FEATURED_CATEGORY', $cat);

			Configuration::updateValue('HOME_FEATURED_RANDOM', (bool)Tools::getValue('random'));
			Configuration::updateValue('HOME_FEATURED_ROLLOVER', Tools::getValue('rollover'));

			if (isset($errors) AND sizeof($errors))
				$output .= $this->displayError(implode('<br />', $errors));
			else
				$output .= $this->displayConfirmation($this->l('Your settings have been updated.'));
		}
		return $output.$this->displayForm();
	}

  // HOME_FEATURED_CATEGORY (float):
  //   1    Root
  //   2    Home or Featured Products
  //  -1    (Product Specials) Pseudo Category
  //  -2    (New Products) Pseudo Category
  //   x    Actual Category Number
	public function displayForm()
	{
    $category = new Category(Configuration::get('HOME_FEATURED_CATEGORY'), (int)Context::getContext()->language->id);
		$categories = array();		
    $categories = $category->getSimpleCategories($this->context->language->id);
    $catsize = count($categories);
    $options = '';
    $options .= '<option value="-1" '.(Configuration::get(HOME_FEATURED_CATEGORY)== -1 ? 'selected="selected"' : '').' />'.$this->l('(Product Specials)').'</option>';
    $options .= '<option value="-2" '.(Configuration::get(HOME_FEATURED_CATEGORY)== -2 ? 'selected="selected"' : '').' />'.$this->l('(New Products)').'</option>';

		foreach ($categories as $row) {
      $options .= '<option value="'.$row['id_category'].'" '.(Configuration::get(HOME_FEATURED_CATEGORY)==$row['id_category'] ? 'selected="selected"' : '').' />'.$row['name'].'</option>';
    }

		$output = '
		<form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" method="post">
			<fieldset><legend><img src="'.$this->_path.'logo.gif" alt="" title="" />'.$this->l('Settings').'</legend>
				<p>'.$this->l('To add products to your homepage, simply add them to the "Home" category, or select any other category').'</p><br />
				<label>'.$this->l('Categories:').'</label>
				<div class="margin-form">
          <select name="cat">
           '.$options.'
    			</select>
				</div>
        <label>'.$this->l('Number of products to be displayed:').'</label>
				<div class="margin-form">
					<input type="text" size="5" name="nbr" value="'.Tools::safeOutput(Tools::getValue('nbr', (int)(Configuration::get('HOME_FEATURED_NBR')))).'" />
					<p class="clear">'.$this->l('Define the number of products that you would like to display on homepage (default: 8).').'</p>
				</div>
				<label>'.$this->l('Random selection:').'</label>
				<div class="margin-form">
    			<input type="checkbox" name="random" id="HOME_FEATURED_RANDOM_on" value="1" '.(Configuration::get(HOME_FEATURED_RANDOM) ? 'checked="checked" ' : '').'/>
				</div>
				<label>'.$this->l('Rollover images:').'</label>
				<div class="margin-form">
    			<input type="radio" name="rollover" id="HOME_FEATURED_ROLLOVER_rotateX" value="rotateX" '.(Configuration::get(HOME_FEATURED_ROLLOVER) == 'rotateX' ? 'checked="checked" ' : '').'/>
    			<label class="t" for="HOME_FEATURED_ROLLOVER_rotateX"> '.$this->l('RotateX').'</label>
    			<input type="radio" name="rollover" id="HOME_FEATURED_ROLLOVER_rotateY" value="rotateY" '.(Configuration::get(HOME_FEATURED_ROLLOVER) == 'rotateY' ? 'checked="checked" ' : '').'/>
    			<label class="t" for="HOME_FEATURED_ROLLOVER_rotateY"> '.$this->l('RotateY').'</label>
    			<input type="radio" name="rollover" id="HOME_FEATURED_ROLLOVER_crossfade" value="crossfade" '.(Configuration::get(HOME_FEATURED_ROLLOVER) == 'crossfade' ? 'checked="checked" ' : '').'/>
    			<label class="t" for="HOME_FEATURED_ROLLOVER_crossfade"> '.$this->l('Crossfade').'</label>
    			<input type="radio" name="rollover" id="HOME_FEATURED_ROLLOVER_zooming" value="zooming" '.(Configuration::get(HOME_FEATURED_ROLLOVER) == 'zooming' ? 'checked="checked" ' : '').'/>
    			<label class="t" for="HOME_FEATURED_ROLLOVER_zooming"> '.$this->l('Zooming').'</label>
    			<input type="radio" name="rollover" id="HOME_FEATURED_ROLLOVER_none" value="none" '.(Configuration::get(HOME_FEATURED_ROLLOVER) == 'none' ? 'checked="checked" ' : '').'/>
    			<label class="t" for="HOME_FEATURED_ROLLOVER_none"> '.$this->l('None').'</label>
				</div>
				<span class="margin-form"><input type="submit" name="submitHomeFeaturedEnhanced" value="'.$this->l('Save').'" class="button" /></span>
			</fieldset>
		</form>';
    
		return $output;
	}
  
	public function hookDisplayHeader($params)
	{
		$this->hookHeader($params);
	}

	public function hookHeader($params)
	{
		$this->context->controller->addCSS(($this->_path).'css/homefeaturedenhanced.css', 'all');

		if (Configuration::get('HOME_FEATURED_ROLLOVER') != 'none') {
			$rollover = Configuration::get('HOME_FEATURED_ROLLOVER');
		  $this->context->controller->addCSS(($this->_path).'css/'.$rollover.'.css', 'all');
    }
	}

	public function hookDisplayHome($params)
	{
		if (Configuration::get('PS_CATALOG_MODE'))
			return ;
    
    // Random Images
		if (Configuration::get('HOME_FEATURED_RANDOM'))
			$random = true;
    else 	
      $random = false;

    // Rollover Images
		if (Configuration::get('HOME_FEATURED_ROLLOVER'))
			$rollover = Configuration::get('HOME_FEATURED_ROLLOVER');

		$nbr = (int)(Configuration::get('HOME_FEATURED_NBR'));
    $id_lang = (int)Context::getContext()->language->id;

    // Get Categories or Pseudo Categories
    if (Configuration::get('HOME_FEATURED_CATEGORY') == -1) 
			$products = Product::getPricesDrop($id_lang, 0, $nbr);
    elseif (Configuration::get('HOME_FEATURED_CATEGORY') == -2)
      $products = Product::getNewProducts($id_lang, 0, $nbr);
    else {
  		$category = new Category((int)Configuration::get('HOME_FEATURED_CATEGORY'), $id_lang);
      $products = $category->getProducts($id_lang, 1, ($nbr ? $nbr : 8), null, null, false, true, $random,($nbr ? $nbr : 8));
    }
    
    // Get Images
    $p=array();
    for($i=0;$i<count($products);$i++){
      $product=$products[$i];
      unset ($pid);
      $pid=$product['id_product'];
      $sql= "SELECT * FROM  `"._DB_PREFIX_."image` WHERE  `id_product` = '$pid' AND cover = '0' ORDER BY  `id_image` ";
      $results = Db::getInstance()->ExecuteS($sql);
      $result=$results[0];
      $p[$pid]=$result['id_image'];
    }

    $this->smarty->assign('addimages',$p);

		$this->smarty->assign(array(
			'products' => $products,
			'add_prod_display' => Configuration::get('PS_ATTRIBUTE_CATEGORY_DISPLAY'),
			'homeSize' => Image::getSize(ImageType::getFormatedName('home')),
			'rollover' => $rollover,
		));
		return $this->display(__FILE__, 'homefeaturedenhanced.tpl');
	}
  
  public function hookAddProduct($params)
	{
		$this->_clearCache('homefeatured.tpl');
	}

	public function hookUpdateProduct($params)
	{
		$this->_clearCache('homefeatured.tpl');
	}

	public function hookDeleteProduct($params)
	{
		$this->_clearCache('homefeatured.tpl');
	}
}
?>
