<?php

namespace Kubio\Core\StyleManager;

use Kubio\Config;
use Kubio\Core\LodashBasic;
use Kubio\Core\Utils as CoreUtils;

use function _\concat;
use function _\uniq;
use function array_merge;
use function count;
use function is_array;
use function is_string;
use function str_contains;
use function str_replace;

class ElementStyleStateRender {

	private $name;
	private $style;

	public function __construct( $name, $style ) {
		$this->name  = $name;
		$this->style = $style;
	}

	public function toCss( $options = null ) {
		$context = array_merge( $options, array( 'state' => $this->name ) );
		return StyleParser::getInstance()->transform(
			LodashBasic::omit( (array) $this->style, 'ancestor' ),
			$context
		);
	}
}

class ElementStyleMediaRender {

	const DEFAULT_STATE = 'normal';
	private $name;
	private $style;
	private $byState            = array();
	private $desktopMediaRender = null;

	public function __construct( $mediaName, $mediaStyle, $desktopMediaRender = null ) {
		$this->name               = $mediaName;
		$this->style              = $mediaStyle;
		$this->desktopMediaRender = $desktopMediaRender;

		if ( $desktopMediaRender ) {
			$mediaStyle = LodashBasic::mergeSkipSeqArray( array(), $desktopMediaRender->style, $mediaStyle );
		}

		$defaultStyle = LodashBasic::get(
			$mediaStyle,
			array( self::DEFAULT_STATE ),
			array()
		);

		if ( $mediaStyle ) {
			foreach ( $mediaStyle as $stateName => $stateStyle ) {
				$mergedStyle =
					$stateName === self::DEFAULT_STATE
						? $stateStyle
						: LodashBasic::merge( array(), $defaultStyle, $stateStyle );
				if ( is_array( $mergedStyle ) && count( $mergedStyle ) ) {
					$this->byState[ $stateName ] = new ElementStyleStateRender(
						$stateName,
						$mergedStyle
					);
				}
			}
		}
	}

	public function toCss( $options = null ) {
		$result = array();

		$result[ self::DEFAULT_STATE ] = isset(
			$this->byState[ self::DEFAULT_STATE ]
		)
			? $this->byState[ self::DEFAULT_STATE ]->toCss( $options )
			: array();

		foreach ( $this->byState as $stateName => $stateStyle ) {
			if ( $stateName !== self::DEFAULT_STATE ) {
				$stateCss = $stateStyle->toCss( $options );
				if ( $stateCss ) {
					$result[ $stateName ] = LodashBasic::diff(
						$stateCss,
						$result[ self::DEFAULT_STATE ]
					);
				}
			}
		}
		return $result;
	}
}

class ElementStyleRender {

	private $byMedias = array();
	private $name;

	private function getOrderedKeys( $byMedias ) {
		return uniq( concat( array( 'desktop' ), array_keys( $byMedias ) ) );
	}

	public function __construct( $name, $style ) {
		$this->name         = $name;
		$byOrderedMedias    = $this->getOrderedKeys( $style );
		$desktopMediaRender = null;
		foreach ( $byOrderedMedias as $mediaName ) {
			$mediaStyle = $style[ $mediaName ];

			$this->byMedias[ $mediaName ] = new ElementStyleMediaRender(
				$mediaName,
				$mediaStyle,
				$desktopMediaRender
			);

			if ( $mediaName == 'desktop' ) {
				$desktopMediaRender = $this->byMedias[ $mediaName ];
			}
		}
	}

	public function toCss( $options = null ) {
		$result          = array();
		$byOrderedMedias = $this->getOrderedKeys( $this->byMedias );
		foreach ( $byOrderedMedias as $mediaName ) {
			$mediaStyle           = $this->byMedias[ $mediaName ];
			$result[ $mediaName ] = $mediaStyle->toCss( $options );

			if ( $mediaName !== 'desktop' ) {
				$result[ $mediaName ] = LodashBasic::diff( $result[ $mediaName ], $result['desktop'] );
			}
		}
		return $result;
	}
}

class AncestorStyleRender {

	private $name;
	private $elementsByName = array();

	public function __construct( $name, $style ) {
		$this->name = $name;
		foreach ( $style as $elementName => $elementStyle ) {
			$this->elementsByName[ $elementName ] = new ElementStyleRender(
				$elementName,
				$elementStyle
			);
		}
	}

	public function toCss( $options ) {
		$result          = array();
		$allowedElements = $options['allowedElements'];
		foreach ( $allowedElements as $elementName ) {
			$elementStyle = LodashBasic::get(
				$this->elementsByName,
				$elementName,
				false
			);
			if ( $elementStyle ) {
				$result[ $elementName ] = $elementStyle->toCss(
					LodashBasic::merge(
						$options,
						array(
							'styledElement' => $elementName,
						)
					)
				);
			}
		}
		return $result;
	}
}

class AncestorsStyleContext {

	public $allowedElements = array();
	public $model           = array();
	public $htmlSupport     = true;

	public function __construct( $model, $allowedElements, $htmlSupport = true ) {
		$this->model           = $model;
		$this->allowedElements = $allowedElements;
		$this->htmlSupport     = $htmlSupport;
	}
}

class AncestorsStyleRender {

	private $ancestorsByName = array();
	private $context         = array();

	private function getOrderedAncestors( $ancestors ) {
		return uniq( concat( array( 'default' ), array_keys( $ancestors ) ) );
	}

	public function __construct( $style, $context = array() ) {
		$this->context     = $context;
		$ancestor          = LodashBasic::get( $style, 'ancestor', array() );
		$ancestors         = LodashBasic::merge(
			array( 'default' => LodashBasic::omit( $style, 'ancestor' ) ),
			$ancestor
		);
		$ordered_ancestors = $this->getOrderedAncestors( $ancestors );

		$defaultNormalizedStyle = array();
		foreach ( $ordered_ancestors as $ancestorName ) {
			$ancestorStyle = $ancestors[ $ancestorName ];
			$normalized    = Utils::normalizeStyle(
				$ancestorStyle,
				array(
					'allowedElements' => $context->allowedElements,
				)
			);

			if ( $ancestorName !== 'default' ) {
				$normalized = LodashBasic::mergeSkipSeqArray( array(), $defaultNormalizedStyle, $normalized );
			} else {
				$defaultNormalizedStyle = $normalized;
			}

			$this->ancestorsByName[ $ancestorName ] = new AncestorStyleRender(
				$ancestorName,
				$normalized
			);
		}
	}

	public function toCss( $composeSelector = null, $styleType = 'shared' ) {
		$result = array();

		$ordered_ancestors = $this->getOrderedAncestors( $this->ancestorsByName );

		foreach ( $ordered_ancestors as $ancestorName ) {
			$ancestorStyle           = $this->ancestorsByName[ $ancestorName ];
			$result[ $ancestorName ] = $ancestorStyle->toCss(
				LodashBasic::merge( (array) $this->context, array('styleType' => $styleType) )
			);
			if ( $ancestorName !== 'default' ) {
				$result[ $ancestorName ] = LodashBasic::diff( $result[ $ancestorName ], $result['default'] );
			}
		}

		$mapped = array();
		foreach ( $result as $ancestorName => $ancestorStyle ) {
			foreach ( $ancestorStyle as $elementName => $elementStyleByMedia ) {
				foreach ( $elementStyleByMedia as $media => $elementMediaStyle ) {
					foreach (
						$elementMediaStyle
						as $elementStateName => $elementStateStyle
					) {
						$selectors = $composeSelector(
							$ancestorName,
							$elementName,
							$elementStateName
						);
						$value     = array();
						LodashBasic::set(
							$value,
							LodashBasic::concat( array( $media ), $selectors ),
							$elementStateStyle
						);
						$mapped = LodashBasic::merge( $mapped, $value );
					}
				}
			}
		}
		return $mapped;
	}
}

class BasicPropsConfig {

	public function __construct( $props ) {
		foreach ( $props as $name => $value ) {
			$this->$name = $value;
		}
	}
}

class StateConfig extends BasicPropsConfig {

	public $selector             = false;
	public $stateRedirectElement = false;
}

class ElementConfig extends BasicPropsConfig {

	const STATE_KEY          = '{{state}}';
	public $usePrefix        = true;
	public $useParentPrefix  = false;
	public $useWrapperPrefix = false;
	public $ancestor         = false;
	public $selector         = false;
	public $statesConfig     = array();
	public $statesById       = array();
	public $selectorPrepend  = false;
	public $prefixWithTag    = false;

	public function getSelector( $state = null ) {
		if ( $this->isSelectorPerState() ) {
			$defaultSelector = LodashBasic::get( $this->selector, 'default' );
			$elementSelector = LodashBasic::get(
				$this->selector,
				$state,
				$defaultSelector
			);
			$elementSelector = str_replace(
				self::STATE_KEY,
				$this->getStateConfig( $state )->selector,
				$elementSelector
			);
		} else {
			$elementSelector = $this->selector;
		}
		return $elementSelector;
	}

	public function isSelectorPerState() {
		return is_array( $this->selector );
	}

	public function getStateConfig( $state ) {
		$props = LodashBasic::merge(
			$this->statesById[ $state ],
			LodashBasic::get( $this->statesConfig, 'default', array() ),
			LodashBasic::get( $this->statesConfig, $state, array() )
		);
		return new StateConfig( $props );
	}
	public function shouldPrependAncestor( $ancestor ) {
		return $this->ancestor !== $ancestor;
	}

	public function shouldAppendStateSelector() {
		if ( $this->isSelectorPerState() ) {
			return false;
		}
		if (
			is_string( $this->selector ) &&
			str_contains( $this->selector, self::STATE_KEY )
		) {
			return false;
		}
		return true;
	}
}

class StyleRender {

	public static $prefixSelectorsByType = array(
		'shared'  => '#kubio',
		'local'   => '#kubio',
		'dynamic' => 'body',
		'global'  => '',
	);

	public $prefixParents;
	protected $parser;
	protected $model                = array();
	protected $styledElementsByName = array();
	protected $styledElementsEnum   = array();

	protected $statesByElement;
	protected $statesById;

	protected $allowedElements;
	protected $htmlSupport;
	protected $baseClass;

	protected $wrapperElement;

	public $useParentPrefix = false;

	public $skipSharedStyleRender = false;

	public function __construct( $options ) {
		$this->parser          = StyleParser::getInstance();
		$this->statesById      = Config::statesById();
		$this->prefixParents   = array();
		$this->useParentPrefix = false;
		$this->htmlSupport     = true;
		$this->baseClass       = '';

		$this->loadOptions( $options );

		$this->allowedElements = LodashBasic::concat(
			array( 'default' ),
			array_keys( $this->styledElementsByName ),
			array_values( $this->styledElementsEnum )
		);
		$this->statesByElement = $this->getStatesByElement();
	}

	public function loadOptions( $options ) {
		foreach ( $options as $name => $value ) {
			if ( property_exists( $this, $name ) ) {
				$this->{$name} = $value;
			}
		}
	}

	public function getStatesByElement() {
		$statesByElement = array();
		foreach ( $this->styledElementsByName as $name => $item ) {
			$statesByElement[ $name ] = LodashBasic::get(
				$item,
				array( 'supports', Config::$statesKey ),
				array( 'normal', 'hover' )
			);
		}
		return $statesByElement;
	}

	public static function normalizeData(
		$mainAttr,
		$styledElementsByName,
		$styledElementsEnum
	) {
		$model = array(
			'style'    => array(
				'local'  => LodashBasic::get( $mainAttr, '_style', array() ),
				'shared' => LodashBasic::get( $mainAttr, 'style', array() ),
			),
			'props'    => array(
				'local'  => LodashBasic::get( $mainAttr, '_props', array() ),
				'shared' => LodashBasic::get( $mainAttr, 'props', array() ),
			),
			'id'       => LodashBasic::get( $mainAttr, 'id' ),
			'styleRef' => LodashBasic::get( $mainAttr, 'styleRef' ),
		);

		foreach ( $styledElementsEnum as $elementName ) {
			if ( ! isset( $styledElementsByName[ $elementName ] ) ) {
				$styledElementsByName[ $elementName ] = array();
			}
		}
		return array(
			'model'                => $model,
			'styledElementsByName' => $styledElementsByName,
			'styledElementsEnum'   => $styledElementsEnum,
		);
	}

	public function export( $dynamicStyle = null ) {
		$style = $this->model->style;
		$css   = array();

		foreach ( $style as $styleType => $styleValue ) {
			if (
				$styleValue &&
				! ( $this->skipSharedStyleRender && $styleType == 'shared' )
			) {
				$css[ $styleType ] = $this->convertStyleToCss(
					$styleValue,
					array(
						'styledElementsByName' => $this->styledElementsByName,
						'styleType'            => $styleType,
					)
				);
			}
		}

		if ( $dynamicStyle ) {
			$css['dynamic'] = $this->convertStyleToCss(
				self::normalizeDynamicStyle( $dynamicStyle ),
				array(
					'styledElementsByName' => $this->styledElementsByName,
					'styleType'            => 'dynamic',
				)
			);
		}

		return $css;
	}

	public function convertStyleToCss( $style, $settings ) {
		$styleType  = LodashBasic::get( $settings, 'styleType', 'shared' );
		$rootPrefix = LodashBasic::get(
			$settings,
			'prefix',
			self::$prefixSelectorsByType[ $styleType ]
		);

		$allowedElements = $this->getStyledElementsNames();

		$ancestorsStyle = new AncestorsStyleRender(
			$style,
			new AncestorsStyleContext(
				$this->model,
				$allowedElements,
				$this->htmlSupport
			)
		);

		$composeSelectorWithPrefix = function (
			$ancestor,
			$element,
			$state
		) use ( $rootPrefix, $styleType ) {
			return $this->composeSelector(
				$rootPrefix,
				$styleType,
				$ancestor,
				$element,
				$state
			);
		};

		$jssByMedia = $ancestorsStyle->toCss( $composeSelectorWithPrefix, $styleType );

		$cssByMedia = array();
		foreach ( $jssByMedia as $media => $jssOnMedia ) {
			LodashBasic::set(
				$cssByMedia,
				array( $media ),
				array( self::renderJssToCss( $jssOnMedia ) )
			);
		}
		return $cssByMedia;
	}

	public function getStyledElementsNames() {
		return LodashBasic::concat( array(), array_keys( $this->styledElementsByName ) );
	}

	public function composeSelector(
		$rootPrefix,
		$styleType,
		$ancestor,
		$element,
		$state
	) {
		$elementConfig = $this->getElementData( $element );
		$selectors     = array();

		if ( $elementConfig->usePrefix ) {
			$selectors[] = $rootPrefix;
		}

		if ( $this->useParentPrefix || $elementConfig->useParentPrefix ) {
			$selectors = array_merge( $selectors, $this->prefixParents );
		}

		$ancestorSelector      =
			$ancestor === 'default' ? '' : $this->ancestorToSelector( $ancestor );
		$shouldPrependAncestor = $elementConfig->shouldPrependAncestor(
			$ancestor
		);
		if ( $ancestorSelector && $shouldPrependAncestor ) {
			$selectors[] = $ancestorSelector;
		}

		$isWrapperElement = $this->wrapperElement === $element;

		$shouldPrefixWithWrapperSelector =
			$this->wrapperElement &&
			( $elementConfig->selector || $elementConfig->useWrapperPrefix ) &&
			$elementConfig->usePrefix &&
			! $isWrapperElement;

		if ( $shouldPrefixWithWrapperSelector ) {
			$selectors[] = $this->composeElementSelector(
				$styleType,
				$ancestor,
				$this->wrapperElement
			);
		}

		$stateConfig = $elementConfig->getStateConfig( $state );

		if ( $stateConfig->stateRedirectElement ) {
			$stateElementSelector = $this->composeElementSelector(
				$styleType,
				$ancestor,
				$stateConfig->stateRedirectElement,
				$state
			);
			$selectors[]          = $stateElementSelector;

			if ( $elementConfig->shouldAppendStateSelector() ) {
				$selectors[] = '&' . $stateConfig->selector;
			}
		}

		$mainSelector = $this->composeElementSelector(
			$styleType,
			$ancestor,
			$element,
			$state
		);

		if ( $ancestorSelector && ! $shouldPrependAncestor ) {
			$mainSelector = $ancestorSelector . $mainSelector;
		}

		$selectors[] = $mainSelector;
		if (
			$elementConfig->shouldAppendStateSelector() &&
			! $stateConfig->stateRedirectElement &&
			$stateConfig->selector
		) {
			$selectors[] = '&' . $stateConfig->selector;
		}

		return $selectors;
	}

	public function getElementData( $elementName ) {
		$elementConfig = isset( $this->styledElementsByName[ $elementName ] )
			? $this->styledElementsByName[ $elementName ]
			: array();
		return new ElementConfig(
			array_merge( array( 'statesById' => $this->statesById ), $elementConfig )
		);
	}

	/**
	 * @param $styleType
	 * @param $ancestor
	 * @param $element
	 *
	 * @return bool|string
	 */
	public function composeElementSelector(
		$styleType,
		$ancestor,
		$element,
		$state = null
	) {
		$elementConfig    = $this->getElementData( $element );
		$elementSelector  = $elementConfig->getSelector( $state );
		$isWrapperElement =
			$this->wrapperElement && $this->wrapperElement === $element;

		if (
			$elementSelector === false ||
			( $isWrapperElement && $elementSelector )
		) {
			$prefixWithTag =
				$elementConfig->prefixWithTag === true
					? $elementConfig->props['tag']
					: $elementConfig->prefixWithTag;

			$composedSelector = $this->componentInstanceClass(
				$element,
				$styleType,
				true,
				$elementConfig->prefixWithTag ? $prefixWithTag : false
			);

			if ( $elementSelector ) {
				if ( $elementConfig->selectorPrepend ) {
					$composedSelector = self::composeSelectors(
						$elementSelector,
						'&' . $composedSelector
					);
				} else {
					$composedSelector = self::composeSelectors(
						$composedSelector,
						$elementSelector
					);
				}
			}

			$elementSelector = $composedSelector;
		}

		return $elementSelector;
	}

	public function componentInstanceClass(
		$name,
		$type,
		$asSelector = false,
		$tag = false
	) {
		$id = $this->model->styleRef;
		switch ( $type ) {
			case 'local':
				$id = $this->componentLocalInstanceId( $type );
				break;
		}

		$style_prefix = apply_filters(
			'kubio/element-style-class-prefix',
			'style-'
		);

		$tagPrefix = $tag ? $tag : '';
		$className = $style_prefix . $id . ( $name ? '-' . $name : '' );
		return $asSelector ? $tagPrefix . '.' . $className : $className;
	}

	public function componentLocalInstanceId( $type ) {
		return $type . '-' . $this->model->id;
	}

	public static function renderJssToCss( $jss, $inherited_selector = '' ) {
		$result     = '';
		$nested     = array();
		$properties = array();

		$style_join = array(
			'new_line' => '',
			'tab'      => '',
		);

		if ( CoreUtils::isDebug() ) {
			$style_join = array(
				'new_line' => "\n",
				'tab'      => "\t",
			);
		}

		foreach ( $jss as $key => $value ) {
			if ( ! is_array( $value ) ) {
				$properties[] = join(
					':',
					array(
						LodashBasic::kebabCase( $key ),
						$value,
					)
				);
			} else {
				$nested[ $key ] = $value;
			}
		}

		if ( count( $properties ) ) {
			$result .=
				$inherited_selector .
				"{{$style_join['new_line']}" .
				join( ';', $properties ) . ';' .
				"{$style_join['new_line']}}{$style_join['new_line']}";
		}

		foreach ( $nested as $nested_selector => $value ) {
			$new_selector = self::composeSelectors(
				$inherited_selector,
				$nested_selector
			);
			$result      .= self::renderJssToCss( $value, $new_selector );
		}

		return $result;
	}

	public static function composeSelectors(
		$inherited_selector_str,
		$nested_selector
	) {
		$selector_parts      = array();
		$selectors           = explode( ',', $nested_selector );
		$inherited_selectors = explode( ',', $inherited_selector_str );

		foreach ( $inherited_selectors as $inherited_selector ) {
			$inherited_selector = \_\trim( $inherited_selector );

			foreach ( $selectors as $selector ) {
				$selector      = \_\trim( $selector );
				$is_compounded = strpos( $selector, '&' ) !== false;

				if ( $is_compounded ) {
					$compounded_selector = str_replace( '&', trim( $inherited_selector ), $selector );
					array_push(
						$selector_parts,
						$compounded_selector
					);
				} else {
					array_push(
						$selector_parts,
						join(
							' ',
							LodashBasic::compact( array( $inherited_selector, trim( $selector ) ) )
						)
					);
				}
			}
		}

		return join( ',', LodashBasic::uniq( $selector_parts ) );
	}

	public static function normalizeDynamicStyle( $dynamicStyleByElements ) {
		$converted = array();
		foreach ( $dynamicStyleByElements as $elementName => $styleByMedia ) {
			$newStyle = array( 'media' => array() );
			if ( isset( $styleByMedia['desktop'] ) ) {
				$newStyle = $styleByMedia['desktop'];
			}
			foreach ( $styleByMedia as $media => $style ) {
				if ( $media !== 'desktop' ) {
					LodashBasic::set( $newStyle, array( 'media', $media ), $style );
				}
			}
			LodashBasic::set(
				$converted,
				array( 'descendants', $elementName ),
				$newStyle
			);
		}
		return $converted;
	}
	public function ancestorToSelector( $name ) {
		$selector = Config::value( array( 'ancestors', $name, 'selector' ) );
		return $selector;
	}
}
