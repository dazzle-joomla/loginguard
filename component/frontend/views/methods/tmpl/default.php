<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2017 Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

/** @var LoginGuardViewMethods $this */

?>
<div id="loginguard-methods-list">
	<div id="loginguard-methods-reset-container" class="well well-large well-lg col-sm-6 col-sm-offset-3 span6 offset3">
		<?php if ($this->tfaActive): ?>
			<a href="<?php echo JRoute::_('index.php?option=com_loginguard&task=methods.disable&' . JFactory::getSession()->getToken() . '=1' . ($this->returnURL ? '&returnurl=' . $this->escape(urlencode($this->returnURL)) : '') . '&user_id=' . $this->user->id) ?>"
			   class="btn btn-danger pull-right">
				<?php echo JText::_('COM_LOGINGUARD_LBL_LIST_REMOVEALL'); ?>
			</a>
		<?php endif; ?>
		<span id="loginguard-methods-reset-message">
            <?php echo JText::sprintf('COM_LOGINGUARD_LBL_LIST_STATUS', JText::_('COM_LOGINGUARD_LBL_LIST_STATUS_' . ($this->tfaActive ? 'ON' : 'OFF'))) ?>
        </span>
	</div>

	<div class="clearfix"></div>

	<?php if (!$this->isAdmin): ?>
	<h3 id="loginguard-methods-list-head">
		<?php echo JText::_('COM_LOGINGUARD_HEAD_LIST_PAGE'); ?>
	</h3>
	<?php endif; ?>
	<div id="loginguard-methods-list-instructions">
		<p>
			<span class="icon icon-info glyphicon glyphicon-info-sign"></span>
			<?php echo JText::_('COM_LOGINGUARD_LBL_LIST_INSTRUCTIONS'); ?>
		</p>
	</div>

	<?php $this->setLayout('list'); echo $this->loadTemplate(); ?>

</div>
