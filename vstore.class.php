<?php


e107::css('vstore','vstore.css');

e107::js('footer-inline', '

$( ".cart-qty" ).keyup(function() {
	
//	alert( "Handler for .change() called." );

	$("#cart-qty-submit").show(0);
	$("#cart-checkout").hide(0);
	
});


','jquery');

require_once('vendor/autoload.php');


use Omnipay\Omnipay;


class vstore_plugin_shortcodes extends e_shortcode
{
	
	protected $vpref = array();
	protected $videos = array();
	protected $symbols = array();
	protected $curSymbol = null;
	protected $currency = null;
	protected $displayCurrency = false;
	protected $categories = array();
	
	public function __construct()
	{
	 	$this->vpref = e107::pref('vstore');	
				
		$this->symbols = array('USD'=>'$','EUR'=>'€', 'CAN'=>'$');
		$currency = !empty($this->vpref['currency']) ? $this->vpref['currency'] : 'USD';

		$this->curSymbol = vartrue($this->symbols[$currency],'$');
		$this->currency = ($this->displayCurrency === true) ? $currency : '';
		
	}

	function setCategories($data)
	{
		$this->categories = $data;
	}
	
	
	function sc_item_id($parm=null)
	{
		return $this->var['item_id'];	
	}
	
	function sc_item_code($parm=null)
	{
		return $this->var['item_code'];	
	}
	
	function sc_item_name($parm=null)
	{
		return e107::getParser()->toHtml($this->var['item_name'], true,'TITLE');	
	}

	function sc_item_description($parm=null)
	{

		$tp = e107::getParser();

		$text = $this->var['item_desc'];

		if(!empty($parm['limit']) && !empty($text))
		{
			$text = $tp->text_truncate($text,$parm['limit']);
		}

		return $tp->toHtml($text, false, 'BODY');
	}
	
	function sc_item_details($parm=null)
	{
		return e107::getParser()->toHtml($this->var['item_details'], true,'BODY');
	}
	

	
	
	
	function sc_item_reviews($parm=null)
	{
		// print_a($this->var['item_reviews']);
		$rev = str_replace("\r","",$this->var['item_reviews']);
		
		$tmp = explode("\n\n",$rev);
		
		if(empty($tmp))
		{
			return null;
		}

		$text = '';

		foreach($tmp as $val)
		{
			list($review, $by) = explode("--",$val);
			$text .= "<blockquote>".$review."<small>".$by."</small></blockquote>";	
		}
		
		return $text;
		//return e107::getParser()->toHtml($this->var['item_reviews'], true, 'BODY');
	}	
	
	function sc_item_related($parm=null)
	{
	
		if(empty($this->var['item_related']))
		{
			return false; 
		}
		$tp = e107::getParser();
		$row = e107::unserialize($this->var['item_related']);
		
	//	return print_a($row, true);
		
		list($table, $chapter) = explode("|", $row['src']);

		$text = '';
		
	
		if($table == 'page_chapters')
		{
			if($chp = e107::getDb()->retrieve('page', '*', 'page_chapter ='.$chapter.' AND page_class IN ('.USERCLASS_LIST.') ORDER BY page_order', true))
			{
				$sc = e107::getScBatch('page',null,'cpage');
				
				$text = "<ul>";
				foreach($chp as $row)
				{
					$sc->setVars($row);
		
					$text .= $tp->parseTemplate("<li>{CPAGELINK}</li>",true,$sc);	
					
					
				}
				
				$text .= "</ul>";
			}
			
		}
		
		return $text;
	}
	
	function sc_item_brand($parm=null)
	{
		return $this->var['item_brand'];	
	}

	function sc_item_pic($parm=null)
	{
		$index = (!empty($parm['item'])) ? intval($parm['item']) : 0; // intval($parm);
		$ival = e107::unserialize($this->var['item_pic']);
		$tp = e107::getParser();
		
		$images = array();
		foreach($ival as $i)
		{
			if($tp->isImage($i['path']))
			{
				$images[] = $i['path'];
			}
		}
		
		$path = vartrue($images[$index]);
		return e107::getParser()->toImage($path,$parm);
	}
	
	function sc_item_video($parm=0)
	{
		$index = intval($parm);
		$ival = e107::unserialize($this->var['item_pic']);
		
		$videos = array();
		foreach($ival as $i)
		{
			if(substr($i['path'],-8) == '.youtube')
			{
				$videos[] = $i['path'];
			}
		}
		
		$path = vartrue($videos[$index]);
		return e107::getParser()->toVideo($path);	
		
	}
	

	
	
	
	
// Categories	
	
	function sc_cat_id($parm=null)
	{
		return $this->var['cat_id'];	
	}
	
	function sc_cat_name($parm=null)
	{
		return e107::getParser()->toHtml($this->var['cat_name'], true,'TITLE');	
	}

	function sc_cat_description($parm=null)
	{
		return e107::getParser()->toHtml($this->var['cat_description'], true, 'BODY');	
	}
	
	function sc_cat_info($parm=null)
	{
		return e107::getParser()->toHtml($this->var['cat_info'], true,'BODY');	
	}	
	
	function sc_cat_image($parm=0)
	{
		return e107::getParser()->thumbUrl($this->var['cat_image']);
	}
	
	function sc_cat_url($parm=null)
	{

		$urlData    = $this->var;
		$route      = 'category';

		if($this->var['cat_parent'] != 0 )
		{
			$urlData['subcat_name'] = $this->var['cat_name'];
			$urlData['subcat_sef']  = $this->var['cat_sef'];
			$urlData['subcat_id']   = $this->var['cat_id'];

			$pid    = $this->var['cat_parent'];
			$parent = $this->categories[$pid];

			$urlData['cat_name']    = $parent['cat_name'];
			$urlData['cat_id']      = $parent['cat_id'];
			$urlData['cat_sef']     = $parent['cat_sef'];

			$route = 'subcategory';
		}

		e107::getDebug()->log($urlData);

		return e107::url('vstore',$route, $urlData);
	}
	
		
	function sc_pref_howtoorder()
	{
		return e107::getParser()->toHtml($this->vpref['howtoorder'],true,'BODY');	
	}
	
	function sc_item_files($parm=0)
	{

		if(empty($this->var['item_files']))
		{
			return null;
		}

		$ival = e107::unserialize($this->var['item_files']);
		
		$id = array();
		
		foreach($ival as $i)
		{
			if(!empty($i['path']) && !empty($i['id']))
			{
				$id[] = intval($i['id']);
			}	
		}

		if(empty($id))
		{
			return null;
		}


		$qry = 'SELECT media_id,media_name FROM #core_media WHERE media_id IN ('.implode(',',$id).') ORDER BY media_name ';
		$files = e107::getDb()->retrieve($qry,true);

		$tp = e107::getParser();
		
		$text = '<ul>';
		foreach($files as $i)
		{
			$bb = '[file='.$i['media_id'].']'.$i['media_name'].'[/file]';
			$text .= '<li>'.$tp->toHtml($bb,true).'</li>';
		}
		
		$text .= '</ul>';
		
		return $text;
	}
	
	
	function sc_item_price($parm=null)
	{
		return ($this->var['item_price'] == '0.00') ? "" : $this->currency.$this->curSymbol.' '.$this->var['item_price'];	
	}	
	
	
	function sc_item_addtocart($parm=null)
	{

		$class = empty($parm['class']) ? 'btn btn-success' : $parm['class'];
		$classo = empty($parm['class0']) ? 'btn btn-default disabled' : $parm['class0'];

		if(empty($this->var['item_inventory']))
		{
			return "<a href='#' class='".$classo."'>Out of Stock</a>";
		}

	
		$url = ($this->var['item_price'] == '0.00' || empty($this->var['item_inventory'])) ? $this->sc_item_url() :e107::url('vstore', 'addtocart', $this->var);
		$label =  ($this->var['item_price'] == '0.00' || empty($this->var['item_inventory'])) ? LAN_READ_MORE : 'Add to cart';
/*
		if($parm == 'url')
		{
			return $url;
		}

		if($parm == 'label')
		{
			return $label;
		}*/



		return '<a class="'.$class.'" href="'.$url.'"><span class="glyphicon glyphicon-shopping-cart"></span> '.$label.'</a>';
	}


	function sc_item_status($parm=null)
	{
		if($this->var['item_inventory'] > 0)
		{
			return '<span class="text-success"><strong>In Stock</strong></span>';
		}	

		return '<span class="text-danger"><strong>Out of Stock</strong></span>';
	}
	
	function sc_item_url($parm=null)
	{
		return e107::url('vstore','product', $this->var);
	}
	
	// -------------
	
	function sc_cart_price($parm=null)
	{
		return $this->curSymbol.$this->var['item_price'];		
	}
	
	function sc_cart_total($parm=null)
	{
		$total = ($this->var['item_price'] * $this->var['cart_qty']);
		return number_format($total,2);
	}
	
	function sc_cart_qty($parm=null)
	{
		if($parm == 'edit')
		{
			 return '<input type="input" name="cartQty['.$this->var['cart_id'].']" class="form-control text-right cart-qty" id="cart-'.$this->var['cart_id'].'" value="'.intval($this->var['cart_qty']).'">';
		}
		
		
		return $this->var['cart_qty'];
	}
	
	
	function sc_cart_removebutton($parm=null)
	{
		return '<button type="submit" name="cartRemove['.$this->var['cart_id'].']" class="btn btn-default" title="Remove">
			<span class="glyphicon glyphicon-trash"></span></button>';
		
	}
	
	function sc_cart_subtotal($parm=null)
	{
		return $this->curSymbol.number_format($this->var['cart_subTotal'], 2);
	}
	
	function sc_cart_shippingtotal($parm=null)
	{
		return $this->curSymbol.number_format($this->var['cart_shippingTotal'], 2);
	}

	function sc_cart_checkout_button()
	{
		$text = '<a href="'.e107::url('vstore','checkout').'" id="cart-checkout"  class="btn btn-success">
		                            Checkout <span class="glyphicon glyphicon-play"></span>
		                        </a>
		                        <button id="cart-qty-submit" style="display:none" type="submit" class="btn btn-warning">Re-Calculate</button>

		';

		return $text;

	}



	function sc_cart_continueshop()
	{
		
		$link = e107::url('vstore','index');
		
		return '
		<a href="'.$link.'" class="btn btn-default">
			<span class="glyphicon glyphicon-shopping-cart"></span> Continue Shopping
		</a>';
	}

	function sc_item_availability()
	{
		if(empty($this->var['item_inventory']))
		{
			return "<span class='label label-warning'>Out Of Stock</span>";
		}

		return "<span class='label label-success'>In Stock</span>";
	}
	
	
	function sc_cart_grandtotal($parm=null)
	{
		return $this->curSymbol.number_format( $this->var['cart_grandTotal'], 2);
	}
		
	

}



class vstore
{
	
	protected 	$cartId             = null;
	protected 	$sc;
	protected 	$perPage            = 9;
	protected   $from               = 0;
	protected 	$categories         = array(); // all categories;
	protected   $categorySEF        = array();
	protected 	$item               = array(); // current item.
	protected   $captionBase        = "Vstore";
	protected   $get                = array();
	protected   $post               = array();
	protected   $categoriesTotal    = 0;
	protected   $action             = array();
	protected   $pref               = array();
	protected   $parentData         = array();

	protected   $gateways           = array(
		'paypal'  => array('title'=>'Paypal', 'icon'=>'fa-paypal'),
		'amazon'  => array('title'=> 'Amazon', 'icon'=>'fa-amazon')
	);



	public function __construct()
	{
		$this->cartId = $this->getCartId();		
		$this->sc = new vstore_plugin_shortcodes();	

		$this->get = $_GET;
		$this->post = $_POST;

		$pref = e107::pref('vstore');


		// get all category data.
		$query = 'SELECT * FROM #vstore_cat ';
		if(!$data = e107::getDb()->retrieve($query, true))
		{

		}

		$this->categoriesTotal = count($data);

		foreach($data as $row)
		{
			$id = $row['cat_id'];
			$this->categories[$id] = $row;
			$sef = vartrue($row['cat_sef'],'--undefined--');
			$this->categorySEF[$sef] = $id;

		}





		$active = array();

		foreach($this->gateways as $k=>$icon)
		{
			$key = $k."_active";
			if(!empty($pref[$key]))
			{
				$active[$k] = $this->getGatewayIcon($k);
			}

		}

		foreach($pref as $k=>$v)
		{
			if(strpos($k,"_")!==false)
			{
				list($gateway,$key) = explode("_", $k,2);

				if(isset($active[$gateway]))
				{
					if($key == 'active') continue;
					$this->pref[$gateway][$key] = $v;

				}
			}

		}

		$this->active = $active;
	}





	function init()
	{
		// print_a($this->get);
		if(!empty($this->get['catsef']))
		{
			$sef = $this->get['catsef'];
			$this->get['cat'] = vartrue($this->categorySEF[$sef],0);
		}

		$this->process();
		

		



		
	}


	private function process()
	{

		if(!empty($this->post['gateway']))
		{
			$this->renderGateway($this->post['gateway']);
		}

		if(varset($this->post['cartQty']))
		{
			$this->updateCart('modify', $this->post['cartQty']);
		}

		if(varset($this->post['cartRemove']))
		{
			$this->updateCart('remove', $this->post['cartRemove']);
		}

		if(!empty($this->get['add']))
		{
			$this->addToCart($this->get['add']);
		}



	}


	public function render()
	{

		$ns = e107::getRender();

		if($this->get['add'])
		{
			$bread = $this->breadcrumb();
			$text = $this->cartView();
			$ns->tablerender($this->captionBase, $bread.$text, 'vstore-cart-view');
			return null;
		}

		if(vartrue($this->get['mode']) == 'checkout')
		{
			// print_a($this->post);
			$bread = $this->breadcrumb();
			$text = $this->checkoutView();
			$ns->tablerender($this->captionBase, $bread.$text, 'vstore-cart-list');
			return null;
		}



		if(vartrue($this->get['mode']) == 'cart')
		{
			// print_a($this->post);
			$bread = $this->breadcrumb();
			$text = $this->cartView();
			$ns->tablerender($this->captionBase, $bread.$text, 'vstore-cart-list');
			return null;
		}





		if($this->get['item'])
		{
			$text = $this->productView($this->get['item']);
			$bread = $this->breadcrumb();
			$ns->tablerender($this->captionBase, $bread.$text, 'vstore-product-view');
			return null;
		}



		if($this->get['cat'])
		{
			if($subCategoryText = $this->categoryList($this->get['cat'],false))
			{
			    $subCategoryText .= "<hr />";
			}


			$text = $this->productList($this->get['cat'], true);
			$bread = $this->breadcrumb();
			$ns->tablerender($this->captionBase, $bread. $subCategoryText.$text, 'vstore-product-list');


		}
		else
		{

			$text = $this->categoryList(0, true);
			$bread = $this->breadcrumb();
			$ns->tablerender($this->captionBase, $bread.$text, 'vstore-category-list');
		}



	}





	private function breadcrumb()
	{
		$frm = e107::getForm();

		$array = array();
		
		$array[] = array('url'=> e107::url('vstore','index'), 'text'=>'Product Brands');
		
		if($this->get['cat'] || $this->get['item'])
		{
			$c = $this->get['cat'];
			$cp = $this->categories[$c]['cat_parent'] ;

			if(!empty($cp))
			{
				$pid = $this->categories[$cp]['cat_id'];
				$url = e107::url('vstore','category', $this->categories[$pid]);
				$array[] = array('url'=> $url, 'text'=>$this->categories[$pid]['cat_name']);
			}

			$id = ($this->get['item']) ? $this->item['item_cat'] : intval($this->get['cat']);
			$url = ($this->get['item']) ? e107::url('vstore','category', $this->categories[$id]) : null;
			$array[] = array('url'=> $url, 'text'=>$this->categories[$id]['cat_name']);	
		}
		
		if($this->get['item'])
		{
			$array[] = array('url'=> null, 'text'=> $this->item['item_name']);		
			
		}

		if($this->get['add'] || $this->get['mode'] == 'cart')
		{
			$array[] = array('url'=> null, 'text'=> "Shopping Cart");
		}

		if($this->get['mode'] == 'checkout')
		{
			$array[] = array('url'=> e107::url('vstore','cart'), 'text'=> "Shopping Cart");
			$array[] = array('url'=> null, 'text'=> "Checkout");

		}


		
		if(ADMIN)
		{
		//	print_a($this->categories);
		//	print_a($this->item);
		//	print_a($array);
		}
		return $frm->breadcrumb($array);	
		
	}


	private function getActiveGateways()
	{


		return $this->active;

	}



	private function checkoutView()
	{
		$active = $this->getActiveGateways();

		if(!empty($active))
		{
			$text = e107::getForm()->open('gateway-select','post');
			$text .= "<div class='vstore-gateway-list row'>";

			foreach($active as $gateway => $icon)
			{

					$text .= "
						<div class='col-md-4'>
						<button class='btn btn-default btn-block' name='gateway' type='submit' value='".$gateway."'>".$icon."
						<h4>".$this->gateways[$gateway]['title']."</h4>
						</button>

						</div>";


			}

			$text .= "</div>";
			$text .= e107::getForm()->close();

			return $text;
		}

		return "No Payment Options Set";


	}


	//  // help http://stackoverflow.com/questions/20756067/omnipay-paypal-integration-with-laravel-4
	// https://www.youtube.com/watch?v=EvfFN0-aBmI
	private function renderGateway($type)
	{
		if(empty($type))
		{
			e107::getMessage()->addError("Invalid Payment Type");
			return false;
		}


		if($type == 'amazon')
		{
			$gateway = Omnipay::create('AmazonPayments');
			$defaults = $gateway->getDefaultParameters();

		//	e107::getDebug()->log($this->pref['paypal']);

			// print_a($defaults);

			e107::getDebug()->log($defaults);

		}




		if($type == 'paypal')
		{
			$gateway = Omnipay::create('PayPal_Express');
			$gateway->setTestMode(true);
			$gateway->setUsername($this->pref['paypal']['username']);
			$gateway->setPassword($this->pref['paypal']['password']);
			$gateway->setSignature($this->pref['paypal']['signature']);

			/*
				$init = array(
					'username'=> $this->pref['paypal']['username'],
					'password'  => $this->pref['paypal']['password'],
					'testMode'  =>true,
					'signature' => $this->pref['paypal']['signature']

				);

				$gateway->initialize($init);

	*/


			$defaults = $gateway->getDefaultParameters();

		//	e107::getDebug()->log($this->pref['paypal']);

			print_a($defaults);

			e107::getDebug()->log($defaults);

			return;

			$response = $gateway->purchase(
                    array(
                        'cancelUrl' => e107::url('vstore', 'cancel', null, array('mode'=>'full')),
                        'returnUrl' => e107::url('vstore', 'return', null, array('mode'=>'full')),
                        'amount' => '25.00',
                        'currency' => 'USD'
                    )
            );



			/*if ($response->isSuccessful()) {

			    // Payment was successful
			    print_a($response);

			} else*/
			if ($response->isRedirect()) {

			    // Redirect to offsite payment gateway
			    $response->redirect();

			}
			else
			{
			    // payment failed: display message to customer
			    $message = $response->getMessage();
			    e107::getMessage()->addError($message);
			}

		}


	}


	private function getGatewayIcon($type)
	{
		$text = !empty($this->gateways[$type]) ? $this->gateways[$type]['icon'] : '';
		return e107::getParser()->toGlyph($text, array('size'=>'5x'));

	}

	
	public function setPerPage($num)
	{
		$this->perPage = intval($num);	
	}

	protected function updateCart($type = 'modify', $array)
	{
		$sql = e107::getDb();
		
		if($type == 'modify')
		{
			foreach($array as $id=>$qty)
			{
				$sql->update('vstore_cart', 'cart_qty = '.intval($qty).' WHERE cart_id = '.intval($id).' LIMIT 1');				
			}
		}
		
		if($type == 'remove')
		{
			foreach($array as $id=>$qty)
			{
				$sql->delete('vstore_cart', 'cart_id = '.intval($id).' LIMIT 1');				
			}	
		}	

		return null;
	}


	protected function getCartId($destroy=false)
	{
		if($destroy === true)
		{
   			setcookie("cartId", false);
			return null;
		}


		if(isset($_COOKIE["cartId"]))
		{
			return $_COOKIE["cartId"];
		}
		else // There is no cookie set. We will set the cookie and return the value of the users session ID
		{

			if(!$_SESSION)
			{
				session_start();
			}

 			setcookie("cartId", session_id(), time() + ((3600 * 24) * 2));
			
			return session_id();
		}
	}


	public function categoryList($parent=0,$np=false)
	{
		
		$this->from = vartrue($this->get['frm'],0);

		$query = 'SELECT * FROM #vstore_cat WHERE cat_parent = '.$parent.' ORDER BY cat_order LIMIT '.$this->from.",".$this->perPage;
		if(!$data = e107::getDb()->retrieve($query, true))
		{
			return false;
		}


	//	$data = $this->categories;
		
		$tp = e107::getParser();

		$text = '
			<div class="row">
		       ';

			
		$template = '
		{SETIMAGE: w=320&h=200&crop=1}
		<div class="vstore-category-list col-sm-4 col-lg-4 col-md-4">
                        <div class="thumbnail">
                            <a href="{CAT_URL}"><img src="{CAT_IMAGE}" alt="" style="height:200px"></a>
                            <div class="caption text-center">
                                <h4><a href="{CAT_URL}">{CAT_NAME}</a></h4>
                                <p class="cat-description"><small>{CAT_DESCRIPTION}</small></p>
                               
                            </div>
                           </div>
                    </div>';
					
		$this->sc->setCategories($this->categories);
		
		foreach($data as $row)
		{
			$this->sc->setVars($row);
			$text .= $tp->parseTemplate($template, true, $this->sc);		
		}
		
		
		
		$text .= '		
			</div>
		';


		if($np === true)
		{
			$nextprev = array(
					'tmpl'			=>'bootstrap',
					'total'			=> $this->categoriesTotal,
					'amount'		=> intval($this->perPage),
					'current'		=> $this->from,
					'url'			=> e107::url('vstore','base')."?frm=[FROM]"
			);
	
			global $nextprev_parms;
		
			$nextprev_parms  = http_build_query($nextprev,false,'&'); // 'tmpl_prefix='.deftrue('NEWS_NEXTPREV_TMPL', 'default').'&total='. $total_downloads.'&amount='.$amount.'&current='.$newsfrom.$nitems.'&url='.$url;
	
			$text .= $tp->parseTemplate("{NEXTPREV: ".$nextprev_parms."}",true);
		}



		return $text;
		

	}
		
	
	public function productList($category=1,$np=false,$templateID = 'list')
	{



		if(!$data = e107::getDb()->retrieve('SELECT SQL_CALC_FOUND_ROWS * FROM #vstore_items WHERE item_cat = '.intval($category).' ORDER BY item_order LIMIT '.$this->from.','.$this->perPage, true))
		{

			return e107::getMessage()->addInfo("No products available in this category")->render();
		}
		
		$count = e107::getDb()->foundRows();
		
		$tp = e107::getParser();

		$template = e107::getTemplate('vstore','vstore', $templateID);

		$text = $tp->parseTemplate($template['start'], true, $this->sc);
		
		foreach($data as $row)
		{
			$id = $row['item_cat'];
			$row['cat_id'] = $row['item_cat'];
			$row['cat_sef'] = $this->categories[$id]['cat_sef'];
			$row['item_sef'] = eHelper::title2sef($row['item_name'],'dashl');
			
			$this->sc->setVars($row);
			$text .= $tp->parseTemplate($template['item'], true, $this->sc);
		}

		$text .= $tp->parseTemplate($template['end'], true, $this->sc);

		if($np === true)
		{
			$nextprev = array(
					'tmpl'			=>'bootstrap',
					'total'			=> $count,
					'amount'		=> intval($this->perPage),
					'current'		=> $this->from,
					'url'			=> e107::url('vstore','base')."?frm=[FROM]"
			);
	
			global $nextprev_parms;
		
			$nextprev_parms  = http_build_query($nextprev,false,'&'); // 'tmpl_prefix='.deftrue('NEWS_NEXTPREV_TMPL', 'default').'&total='. $total_downloads.'&amount='.$amount.'&current='.$newsfrom.$nitems.'&url='.$url;
	
			$text .= $tp->parseTemplate("{NEXTPREV: ".$nextprev_parms."}",true);
		}


		return $text;
		

	}	
	
	
	
	protected function productView($id=0)
	{
		if(!$row = e107::getDb()->retrieve('SELECT * FROM #vstore_items WHERE item_id = '.intval($id).'  LIMIT 1',true))
		{
			e107::getMessage()->addInfo("No products available in this category");
			return null;
		}
		
		$this->item = $row[0];
		
		$tp = e107::getParser();
		$frm = e107::getForm();
		
		$catid = $this->item['item_cat'];
		$data = array_merge($row[0],$this->categories[$catid]);
		
	//	print_a($data);
		
		$this->sc->setVars($data);
		$this->sc->wrapper('vstore/item');

        $tmpl = e107::getTemplate('vstore');


        $text = $tmpl['item']['main'];

		$tabData = array();

		if(!empty($data['item_details']))
		{
			$tabData['details'] =  array('caption'=>'Details', 'text'=>$tmpl['item']['details']);
		}

		if($media = e107::unserialize($data['item_pic']))
		{
			foreach($media as $v)
			{
				if($tp->isVideo($v['path']))
				{
					$tabData['videos']  = array('caption'=>'Videos', 'text'=> $tmpl['item']['videos']);
					break;
				}
			}
		}

		if(!empty($data['item_reviews']))
		{
			$tabData['reviews'] = array('caption'=>'Reviews', 'text'=> $tmpl['item']['reviews']);
		}
		
		
		if(!empty($data['item_related']))
		{
			$tmp = e107::unserialize($data['item_related']);
			if(!empty($tmp['src']))
			{	
				$tabData['related']	= array('caption'=>varset($tmp['caption'],'Related'), 'text'=> $tmpl['item']['related']);
			}		
		}

		if(!empty($data['item_files']))
		{
			$tmp = e107::unserialize($data['item_files']);
			if(!empty($tmp[0]['path']))
			{
				$tabData['files']		= array('caption'=>'Downloads', 'text'=> $tmpl['item']['files']);
			}
		}
		
		if(!empty($data['cat_info']))
		{
			$tabData['howto']		= array('caption'=>'How to Order', 'text'=> $tmpl['item']['howto']);
		}

		if(!empty($tabData))
		{
			$text .= $frm->tabs($tabData);
		}
	//	print_a($text);
		$parsed = $tp->parseTemplate($text, true, $this->sc);

		return $parsed;
	}
	
	
	protected function cartData()
	{
		
		
		
	}
	
	
	protected function addToCart($id)
	{
		$sql = e107::getDb();
		
		// Item Exists. 
		if($rec = $sql->retrieve('SELECT cart_id FROM #vstore_cart WHERE cart_session = "'.$this->cartId.'" AND cart_item = '.intval($id).' LIMIT 1'))
		{

			if($sql->update('vstore_cart', 'cart_qty = cart_qty +1 WHERE cart_id = '.$rec))
			{
				return true;
			}
		
			return false;	
		}
		
		
		$insert = array(
			'cart_id' 			=> 0,
			'cart_session' 		=> $this->cartId,
	  		'cart_e107_user'	=> USERID,
	  		'cart_status'		=> '',
	  		'cart_item'			=> intval($id),
	  		'cart_qty'			=> 1
  		);

		// Add new Item. 
		return $sql->insert('vstore_cart', $insert);
			
		
	}


	public function getCartData()
	{
		return e107::getDb()->retrieve('SELECT c.*,i.* FROM #vstore_cart AS c LEFT JOIN #vstore_items as i ON c.cart_item = i.item_id WHERE c.cart_session = "'.$this->cartId.'" AND c.cart_status ="" ', true);
	}



	protected function cartView()
	{
		if(!$data = $this->getCartData() )
		{
			return e107::getMessage()->addInfo("Your cart is empty.")->render();


		}
		
		$tp = e107::getParser();
		$frm = e107::getForm();
		
		$text = $frm->open('cart','post', e107::url('vstore','cart'));
		
		$text .= '
		
		
		    <div class="row">
		        <div class="col-sm-12 col-md-12">
		            <table class="table table-hover">
		                <thead>
		                    <tr>
		                        <th>Product</th>
		                         <th> </th>
		                        <th>Quantity</th>
		                        <th class="text-right">Price</th>
		                        <th class="text-right">Total</th>

		                    </tr>
		                </thead>
		                <tbody>';
			
			
			
			$template = '
						{SETIMAGE: w=72&h=72&crop=1}
		                    <tr>
		                        <td>
		                        <div class="media">
		                        	<div class="media-left">
		                            <a href="{ITEM_URL}">{ITEM_PIC: class=media-object}</a>
		                           </div>
		                             <div class="media-body">
		                                <h4 class="media-heading"><a href="{ITEM_URL}">{ITEM_NAME}</a></h4>
		                                <h5 class="media-heading"> by <a href="#">Brand name</a></h5>
		                                <span>Status: </span>{ITEM_STATUS}
		                            </div>
		                        </div></td>
		                         <td class="col-sm-1 col-md-1 text-center">{CART_REMOVEBUTTON}</td>
		                        <td class="col-sm-1 col-md-1 text-center">{CART_QTY=edit} </td>
		                        <td class="col-sm-1 col-md-1 text-right">{CART_PRICE}</td>
		                        <td class="col-sm-1 col-md-1 text-right"><strong>{CART_TOTAL}</strong></td>

		                    </tr>
		           ';
			
			
			
			
			
			$subTotal 		= 0;
			$shippingTotal 	= 0;
		//	$grandTotal		= 0;
						
			foreach($data as $row)
			{
			
				$subTotal += ($row['cart_qty'] * $row['item_price']);	
				$shippingTotal	+= ($row['cart_qty'] * $row['item_shipping']);	
				
				
				$this->sc->setVars($row);
				$text .= $tp->parseTemplate($template, true, $this->sc);	
			}
			
			$grandTotal = $subTotal + $shippingTotal;
			$totals = array('cart_subTotal' => $subTotal, 'cart_shippingTotal'=>$shippingTotal, 'cart_grandTotal'=>$grandTotal);

			$this->sc->setVars($totals);

			
			$footer = '     
		                   <tr>
		                   <td>   </td>
		                        <td colspan="2"><div class="text-right" ></div></td>
		                        <td><h5>Subtotal</h5></td>
		                        <td class="text-right"><h5><strong>{CART_SUBTOTAL}</strong></h5></td>

		                    </tr>
		                    <tr>

								<td>   </td>
		                        <td colspan="3" class="text-right"><h5>Estimated shipping</h5></td>
		                        <td class="text-right"><h5><strong>{CART_SHIPPINGTOTAL}</strong></h5></td>


		                    </tr>
		                    <tr>
		                        <td>   </td>
		                        <td>   </td>
								 <td>   </td>
		                        <td><h3>Total</h3></td>
		                        <td class="text-right"><h3><strong>{CART_GRANDTOTAL}</strong></h3></td>


		                    </tr>
		                    <tr>
		                        <td colspan="2">
		                       {CART_CONTINUESHOP}</td>
		                        <td colspan="3" class="text-right">
		                        {CART_CHECKOUT_BUTTON}
		                        </td>

		                    </tr>
		                </tbody>
		            </table>
		        </div>
		    </div>
	
		';
		
		
		$text .= $tp->parseTemplate($footer, true, $this->sc);	
		
		$text .= $frm->close();

		return $text;
	//	$ns->tablerender("Shopping Cart",$text,'vstore-view-cart');
		
		return null;
	}
	
	
	
	
	
	
	
	
	
	
	
}


