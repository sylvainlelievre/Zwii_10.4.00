<article>
	<div class="row">
		<div class="col10">
			<div class="blogDate">
				<i class="far fa-calendar-alt"></i>
				<?php echo strftime('%d %B %Y', $this->getData(['module', $this->getUrl(0), $this->getUrl(1), 'publishedOn'])); ?>
					à <?php echo utf8_encode(strftime('%H:%M', $this->getData(['module', $this->getUrl(0), $this->getUrl(1), 'publishedOn']))); ?>
			</div>
		</div>
		<?php  if($this->getUser('group') >= self::GROUP_ADMIN): ?>
			<div class="col2">
				<?php echo template::button('blogEdit', [
							'href' => helper::baseUrl() . $this->getUrl(0) . '/edit/' . $this->getUrl(1) . '/' . $_SESSION['csrf'],
							'value' => 'Editer'
				]); ?>
			</div>
		<?php endif; ?>
	</div>
		<?php $pictureSize =  $this->getData(['module', $this->getUrl(0), $this->getUrl(1), 'pictureSize']) === null ? '100' : $this->getData(['module', $this->getUrl(0), $this->getUrl(1), 'pictureSize']); ?>
		<?php if ($this->getData(['module', $this->getUrl(0), $this->getUrl(1), 'hidePicture']) == false) {
			echo '<img class="blogArticlePicture blogArticlePicture' . $this->getData(['module', $this->getUrl(0), $this->getUrl(1), 'picturePosition']) .
			' pict' . $pictureSize . '" src="' . helper::baseUrl(false) . self::FILE_DIR.'source/' . $this->getData(['module', $this->getUrl(0), $this->getUrl(1), 'picture']) .
			'" alt="' . $this->getData(['module', $this->getUrl(0), $this->getUrl(1), 'picture']) . '">';
		} ?>
	<?php echo $this->getData(['module', $this->getUrl(0), $this->getUrl(1), 'content']); ?>
	<p class="clearBoth signature"><?php echo $module::$articleSignature;?></p>
	<?php if($this->getData(['module', $this->getUrl(0), $this->getUrl(1), 'closeComment'])): ?>
		<p>Cet article ne reçoit pas de commentaire.</p>
	<?php else: ?>
		<h3 id="comment">
			<?php $commentsNb = count($module::$comments); ?>
			<?php $s =  $commentsNb === 1 ? '': 's' ?>
			<?php echo $commentsNb > 0 ? $commentsNb . ' ' .  'commentaire' . $s : 'Pas encore de commentaire'; ?>
		</h3>
		<?php echo template::formOpen('blogArticleForm'); ?>
			<?php echo template::text('blogArticleCommentShow', [
				'placeholder' => 'Rédiger un commentaire...',
				'readonly' => true
			]); ?>
			<div id="blogArticleCommentWrapper" class="displayNone">
					<?php if($this->getUser('password') === $this->getInput('ZWII_USER_PASSWORD')): ?>
					<?php echo template::text('blogArticleUserName', [
						'label' => 'Nom',
						'readonly' => true,
						'value' => $module::$editCommentSignature
					]); ?>
					<?php echo template::hidden('blogArticleUserId', [
						'value' => $this->getUser('id')
					]); ?>
				<?php else: ?>
					<div class="row">
						<div class="col9">
							<?php echo template::text('blogArticleAuthor', [
								'label' => 'Nom'
							]); ?>
						</div>
						<div class="col1 textAlignCenter verticalAlignBottom">
							<div id="blogArticleOr">Ou</div>
						</div>
						<div class="col2 verticalAlignBottom">
							<?php echo template::button('blogArticleLogin', [
								'href' => helper::baseUrl() . 'user/login/' . str_replace('/', '_', $this->getUrl()) . '__comment',
								'value' => 'Connexion'
							]); ?>
						</div>
					</div>
				<?php endif; ?>
				<?php echo template::textarea('blogArticleContent', [
						'label' => 'Commentaire avec maximum '.$this->getData(['module', $this->getUrl(0), $this->getUrl(1), 'commentMaxlength']).' caractères',
						'class' => 'editorWysiwygComment',
						'noDirty' => true,
						'maxlength' => $this->getData(['module', $this->getUrl(0), $this->getUrl(1), 'commentMaxlength'])
				]); ?>
				<div id="blogArticleContentAlarm"> </div>
				<?php if($this->getUser('password') !== $this->getInput('ZWII_USER_PASSWORD')): ?>
					<div class="row">
						<div class="col4">
							<?php echo template::captcha('blogArticleCaptcha'); ?>
						</div>
					</div>
				<?php endif; ?>
				<div class="row">
					<div class="col2 offset8">
						<?php echo template::button('blogArticleCommentHide', [
							'class' => 'buttonGrey',
							'value' => 'Annuler'
						]); ?>
					</div>
					<div class="col2">
						<?php echo template::submit('blogArticleSubmit', [
							'value' => 'Envoyer',
							'ico' => ''
						]); ?>
					</div>
				</div>
			</div>
		<?php echo template::formClose(); ?>
	<?php endif;?>

	<div class="row">
		<div class="col12">
			<?php foreach($module::$comments as $commentId => $comment): ?>
				<div class="block">
					<h4><?php echo $module::$commentsSignature[$commentId]; ?>
						le <?php echo strftime('%d %B %Y - %H:%M', $comment['createdOn']); ?>
					</h4>
					<?php echo $comment['content']; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php echo $module::$pages; ?>
</article>