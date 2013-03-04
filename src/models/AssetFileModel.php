<?php
namespace Craft;

/**
 *
 */
class AssetFileModel extends BaseElementModel
{
	protected $elementType = ElementType::Asset;

	/**
	 * User the filename as the string representation.
	 *
	 * @return string
	 */
	function __toString()
	{
		return $this->filename;
	}

	/**
	 * @access protected
	 * @return array
	 */
	protected function defineAttributes()
	{
		return array_merge(parent::defineAttributes(), array(
			'sourceId'		=> AttributeType::Number,
			'folderId'		=> AttributeType::Number,
			'contentId'		=> AttributeType::Number,
			'filename'		=> AttributeType::String,
			'originalName'	=> AttributeType::String,
			'kind'			=> AttributeType::String,
			'width'			=> AttributeType::Number,
			'height'		=> AttributeType::Number,
			'size'			=> AttributeType::Number,
			'dateModified'  => AttributeType::DateTime
		));
	}

	/**
	 * @return AssetFolderModel|null
	 */
	public function getFolder()
	{
		return craft()->assets->getFolderById($this->folderId);
	}

	/**
	 * Returns the URL to the file.
	 *
	 * @param string|null $sizeHandle
	 * @return string|null
	 */
	public function getUrl($sizeHandle = null)
	{
		$sourceType = craft()->assetSources->getSourceTypeById($this->sourceId);
		if ($sourceType->isSourceLocal())
		{
			$base = $sourceType->getSettings()->url;
		}
		else
		{
			$base = $sourceType->getSettings()->urlPrefix;
		}
		return $base.$this->getFolder()->fullPath.($sizeHandle ? '_'.$sizeHandle.'/' : '').$this->filename;
	}

	/**
	 * Get the thumb's URL.
	 *
	 * @param int $width
	 * @param int $height
	 * @return string
	 */
	public function getThumbUrl($width = 125, $height = 125)
	{
		if (!is_numeric($width))
		{
			$width = 125;
		}
		if (!is_numeric($height))
		{
			$height = 125;
		}

		return UrlHelper::getResourceUrl('assetthumbs/'.$this->id.'/'.$width.'x'.$height);
	}

	/**
	 * Get the file extension.
	 *
	 * @return mixed
	 */
	public function getExtension()
	{
		return pathinfo($this->filename, PATHINFO_EXTENSION);
	}

	/**
	 * Return thumbnail data.
	 *
	 * @param $maxWidth
	 * @param $maxHeight
	 * @return bool|object
	 */
	public function getThumbData($maxWidth, $maxHeight)
	{

		if ($this->kind != "image")
		{
			return false;
		}

		// treat the image as a horizontal?
		if (($this->height / $this->width) <= ($maxHeight / $maxWidth))
		{
			$thumbWidth = $maxWidth;
			$thumbHeight = round(($maxWidth / $this->width) * $this->height);
		}
		else
		{
			$thumbHeight = $maxHeight;
			$thumbWidth = round(($maxHeight / $this->height) * $this->width);
		}

		return (object) array(
			'url'     => $this->getThumbUrl($thumbWidth, $thumbHeight),
			'url2X'  => $this->getThumbUrl($thumbWidth*2, $thumbHeight*2),
			'width'   => $thumbWidth,
			'height'  => $thumbHeight,
		);
	}

	/**
	 * Get image height.
	 *
	 * @param string $handle optional transform handle for which to get thumbnail.
	 * @return bool|float|mixed
	 */

	public function getHeight($handle = '')
	{
		return $this->_getDimension('height', $handle);
	}

	/**
	 * Get image width.
	 *
	 * @param string $handle optional transform handle for which to get thumbnail.
	 * @return bool|float|mixed
	 */
	public function getWidth($handle = '')
	{
		return $this->_getDimension('width', $handle);
	}

	/**
	 * Return a dimension of the image.
	 *
	 * @param $dimension 'height' or 'width'
	 * @param $handle
	 * @return bool|float|mixed
	 */
	private function _getDimension($dimension, $handle)
	{
		if ($this->kind != 'image' or !in_array($dimension, array('height', 'width')))
		{
			return false;
		}

		if (empty($handle))
		{
			return $this->$dimension;
		}

		try {
			$transform = craft()->assetTransforms->getAssetTransform($handle);
			switch ($transform->mode)
			{
				case 'stretchToFit':
				{
					return $transform->$dimension;
				}

				case 'scaleToFit':
				{
					if ($this->height > $transform->height || $this->width > $transform->width)
					{
						$factor = max($this->width / $transform->width, $this->height / $transform->height);
						return round($this->$dimension / $factor);
					}
					return $this->$dimension;
				}

				case 'scaleAndCrop':
				{
					if ($this->height > $transform->height || $this->width > $transform->width)
					{
						return $transform->$dimension;
					}
					return $this->$dimension;
				}
			}
		}
		catch (Exception $exception)
		{
			// Oh well.
		}

		return false;
	}
}
