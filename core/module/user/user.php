<?php

/**
 * This file is part of Zwii.
 *
 * For full copyright and license information, please see the LICENSE
 * file that was distributed with this source code.
 *
 * @author Rémi Jean <remi.jean@outlook.com>
 * @copyright Copyright (C) 2008-2018, Rémi Jean
 * @license GNU General Public License, version 3
 * @link http://zwiicms.fr/
 */

class user extends common {

	public static $actions = [
		'add' => self::GROUP_ADMIN,
		'delete' => self::GROUP_ADMIN,
		'edit' => self::GROUP_MEMBER,
		'forgot' => self::GROUP_VISITOR,
		'index' => self::GROUP_ADMIN,
		'login' => self::GROUP_VISITOR,
		'logout' => self::GROUP_MEMBER,
		'reset' => self::GROUP_VISITOR
	];

	public static $users = [];

	public static $userId = '';

	public static $userLongtime = false;

	/**
	 * Ajout
	 */
	public function add() {
		// Soumission du formulaire
		if($this->isPost()) {
			$check=true;
			// L'identifiant d'utilisateur est indisponible
			$userId = $this->getInput('userAddId', helper::FILTER_ID, true);
			if($this->getData(['user', $userId])) {
				self::$inputNotices['userAddId'] = 'Identifiant déjà utilisé';
				$check=false;
			}
			// Double vérification pour le mot de passe
			if($this->getInput('userAddPassword', helper::FILTER_STRING_SHORT, true) !== $this->getInput('userAddConfirmPassword', helper::FILTER_STRING_SHORT, true)) {
				self::$inputNotices['userAddConfirmPassword'] = 'Incorrect';
				$check = false;
			}
			// Crée l'utilisateur
			$userFirstname = $this->getInput('userAddFirstname', helper::FILTER_STRING_SHORT, true);
			$userLastname = $this->getInput('userAddLastname', helper::FILTER_STRING_SHORT, true);
			$userMail = $this->getInput('userAddMail', helper::FILTER_MAIL, true);
			
			// Stockage des données
			$this->setData([
				'user',
				$userId,
				[
					'firstname' => $userFirstname,
					'forgot' => 0,
					'group' => $this->getInput('userAddGroup', helper::FILTER_INT, true),
					'lastname' => $userLastname,
					'mail' => $userMail,
					'password' => $this->getInput('userAddPassword', helper::FILTER_PASSWORD, true),
				]
			]);

			// Envoie le mail
			$sent = true;
			if($this->getInput('userAddSendMail', helper::FILTER_BOOLEAN) && $check === true) {
				$sent = $this->sendMail(
					$userMail,
					'Compte créé sur ' . $this->getData(['config', 'title']),
					'Bonjour <strong>' . $userFirstname . ' ' . $userLastname . '</strong>,<br><br>' .
					'Un administrateur vous a créé un compte sur le site ' . $this->getData(['config', 'title']) . '. Vous trouverez ci-dessous les détails de votre compte.<br><br>' .
					'<strong>Identifiant du compte :</strong> ' . $this->getInput('userAddId') . '<br>' .
					'<strong>Mot de passe du compte :</strong> ' . $this->getInput('userAddPassword') . '<br><br>' .
					'<small>Nous ne conservons pas les mots de passe, en conséquence nous vous conseillons de conserver ce message tant que vous ne vous êtes pas connecté. Vous pourrez modifier votre mot de passe après votre première connexion.</small>',
					null
				);
			}
			// Valeurs en sortie
			$this->addOutput([
				'redirect' => helper::baseUrl() . 'user',
				'notification' => $sent === true ? 'Utilisateur créé' : $sent,
				'state' => $sent === true ? true : null
			]);
		}
		// Valeurs en sortie
		$this->addOutput([
			'title' => 'Nouvel utilisateur',
			'view' => 'add'
		]);
	}

	/**
	 * Suppression
	 */
	public function delete() {
		// Accès refusé
		if(
			// L'utilisateur n'existe pas
			$this->getData(['user', $this->getUrl(2)]) === null
			// Groupe insuffisant
			AND ($this->getUrl('group') < self::GROUP_MODERATOR)
		) {
			// Valeurs en sortie
			$this->addOutput([
				'access' => false
			]);
		}
		// Jeton incorrect
		elseif ($this->getUrl(3) !== $_SESSION['csrf']) {
			// Valeurs en sortie
			$this->addOutput([
				'redirect' => helper::baseUrl() . 'user',
				'notification' => 'Action non autorisée'
			]);
		}
		// Bloque la suppression de son propre compte
		elseif($this->getUser('id') === $this->getUrl(2)) {
			// Valeurs en sortie
			$this->addOutput([
				'redirect' => helper::baseUrl() . 'user',
				'notification' => 'Impossible de supprimer votre propre compte'
			]);
		}
		// Suppression
		else {
			$this->deleteData(['user', $this->getUrl(2)]);
			// Valeurs en sortie
			$this->addOutput([
				'redirect' => helper::baseUrl() . 'user',
				'notification' => 'Utilisateur supprimé',
				'state' => true
			]);
		}
	}

	/**
	 * Édition
	 */
	public function edit() {
		if ($this->getUrl(3) !== $_SESSION['csrf'] &&
			$this->getUrl(4) !== $_SESSION['csrf']) {
			// Valeurs en sortie
			$this->addOutput([
				'redirect' => helper::baseUrl() . 'user',
				'notification' => 'Action  non autorisée'
			]);
		}
		// Accès refusé
		if(
			// L'utilisateur n'existe pas
			$this->getData(['user', $this->getUrl(2)]) === null
			// Droit d'édition
			AND (
				// Impossible de s'auto-éditer
				(
					$this->getUser('id') === $this->getUrl(2)
					AND $this->getUrl('group') <= self::GROUP_VISITOR
				)
				// Impossible d'éditer un autre utilisateur
				OR ($this->getUrl('group') < self::GROUP_MODERATOR)
			)
		) {
			// Valeurs en sortie
			$this->addOutput([
				'access' => false
			]);
		}
		// Accès autorisé
		else {
			// Soumission du formulaire
			if($this->isPost()) {
				// Double vérification pour le mot de passe
				$newPassword = $this->getData(['user', $this->getUrl(2), 'password']);
				if($this->getInput('userEditNewPassword')) {
					// L'ancien mot de passe est correct
					if(password_verify($this->getInput('userEditOldPassword'), $this->getData(['user', $this->getUrl(2), 'password']))) {
						// La confirmation correspond au mot de passe
						if($this->getInput('userEditNewPassword') === $this->getInput('userEditConfirmPassword')) {
							$newPassword = $this->getInput('userEditNewPassword', helper::FILTER_PASSWORD, true);
							// Déconnexion de l'utilisateur si il change le mot de passe de son propre compte
							if($this->getUser('id') === $this->getUrl(2)) {
								helper::deleteCookie('ZWII_USER_ID');
								helper::deleteCookie('ZWII_USER_PASSWORD');
							}
						}
						else {
							self::$inputNotices['userEditConfirmPassword'] = 'Incorrect';
						}
					}
					else {
						self::$inputNotices['userEditOldPassword'] = 'Incorrect';
					}
				}
				// Modification du groupe
				if(
					$this->getUser('group') === self::GROUP_ADMIN
					AND $this->getUrl(2) !== $this->getUser('id')
				) {
					$newGroup = $this->getInput('userEditGroup', helper::FILTER_INT, true);
				}
				else {
					$newGroup = $this->getData(['user', $this->getUrl(2), 'group']);
				}
				// Modifie l'utilisateur
				$this->setData([
					'user',
					$this->getUrl(2),
					[
						'firstname' => $this->getInput('userEditFirstname', helper::FILTER_STRING_SHORT, true),
						'forgot' => 0,
						'group' => $newGroup,
						'lastname' => $this->getInput('userEditLastname', helper::FILTER_STRING_SHORT, true),
						'mail' => $this->getInput('userEditMail', helper::FILTER_MAIL, true),
						'password' => $newPassword,
						'connectFail' => $this->getData(['user',$this->getUrl(2),'connectFail']),
						'connectTimeout' => $this->getData(['user',$this->getUrl(2),'connectTimeout']),
						'accessUrl' => $this->getData(['user',$this->getUrl(2),'accessUrl']),
						'accessTimer' => $this->getData(['user',$this->getUrl(2),'accessTimer']),
						'accessCsrf' => $this->getData(['user',$this->getUrl(2),'accessCsrf'])
					]
				]);
				// Redirection spécifique si l'utilisateur change son mot de passe
				if($this->getUser('id') === $this->getUrl(2) AND $this->getInput('userEditNewPassword')) {
					$redirect = helper::baseUrl() . 'user/login/' . str_replace('/', '_', $this->getUrl());
				}
				// Redirection si retour en arrière possible
				elseif($this->getUrl(3)) {
					$redirect = helper::baseUrl() . 'user';
				}
				// Redirection normale
				else {
					$redirect = helper::baseUrl() . $this->getUrl();
				}
				// Valeurs en sortie
				$this->addOutput([
					'redirect' => $redirect,
					'notification' => 'Modifications enregistrées',
					'state' => true
				]);
			}
			// Valeurs en sortie
			$this->addOutput([
				'title' => $this->getData(['user', $this->getUrl(2), 'firstname']) . ' ' . $this->getData(['user', $this->getUrl(2), 'lastname']),
				'view' => 'edit'
			]);
		}
	}

	/**
	 * Mot de passe perdu
	 */
	public function forgot() {
		// Soumission du formulaire
		if($this->isPost()) {
			$userId = $this->getInput('userForgotId', helper::FILTER_ID, true);
			if($this->getData(['user', $userId])) {
				// Enregistre la date de la demande dans le compte utilisateur
				$this->setData(['user', $userId, 'forgot', time()]);
				// Crée un id unique pour la réinitialisation
				$uniqId = md5(json_encode($this->getData(['user', $userId])));
				// Envoi le mail
				$sent = $this->sendMail(
					$this->getData(['user', $userId, 'mail']),
					'Réinitialisation de votre mot de passe',
					'Bonjour <strong>' . $this->getData(['user', $userId, 'firstname']) . ' ' . $this->getData(['user', $userId, 'lastname']) . '</strong>,<br><br>' .
					'Vous avez demandé à changer le mot de passe lié à votre compte. Vous trouverez ci-dessous un lien vous permettant de modifier celui-ci.<br><br>' .
					'<a href="' . helper::baseUrl() . 'user/reset/' . $userId . '/' . $uniqId . '" target="_blank">' . helper::baseUrl() . 'user/reset/' . $userId . '/' . $uniqId . '</a><br><br>' .
					'<small>Si nous n\'avez pas demandé à réinitialiser votre mot de passe, veuillez ignorer ce mail.</small>',
					null
				);
				// Valeurs en sortie
				$this->addOutput([
					'notification' => ($sent === true ? 'Un mail vous a été envoyé afin de continuer la réinitialisation' : $sent),
					'state' => ($sent === true ? true : null)
				]);
			}
			// L'utilisateur n'existe pas
			else {
				// Valeurs en sortie
				$this->addOutput([
					'notification' => 'Cet utilisateur n\'existe pas'
				]);
			}
		}
		// Valeurs en sortie
		$this->addOutput([
			'display' => self::DISPLAY_LAYOUT_LIGHT,
			'title' => 'Mot de passe oublié',
			'view' => 'forgot'
		]);
	}

	/**
	 * Liste des utilisateurs
	 */
	public function index() {
		$userIdsFirstnames = helper::arrayCollumn($this->getData(['user']), 'firstname');
		ksort($userIdsFirstnames);
		foreach($userIdsFirstnames as $userId => $userFirstname) {
			if ($this->getData(['user', $userId, 'group'])) {
				self::$users[] = [
					$userId,
					$userFirstname . ' ' . $this->getData(['user', $userId, 'lastname']),
					self::$groups[$this->getData(['user', $userId, 'group'])],
					template::button('userEdit' . $userId, [
						'href' => helper::baseUrl() . 'user/edit/' . $userId . '/back/'. $_SESSION['csrf'],
						'value' => template::ico('pencil')
					]),
					template::button('userDelete' . $userId, [
						'class' => 'userDelete buttonRed',
						'href' => helper::baseUrl() . 'user/delete/' . $userId. '/' . $_SESSION['csrf'],
						'value' => template::ico('cancel')
					])
				];
			}
		}
		// Valeurs en sortie
		$this->addOutput([
			'title' => 'Liste des utilisateurs',
			'view' => 'index'
		]);
	}

	/**
	 * Connexion
	 */
	public function login() {
		// Soumission du formulaire
		if($this->isPost()) {
			// Check la captcha
			if(
				$this->getData(['config','connect','captcha'])
				AND password_verify($this->getInput('userLoginCaptcha', helper::FILTER_INT), $this->getInput('userLoginCaptchaResult') ) === false )
			{
				self::$inputNotices['userLoginCaptcha'] = 'Incorrect';
			} else {
				// Lire Id du compte
				$userId = $this->getInput('userLoginId', helper::FILTER_ID, true);
				/**
				 * Aucun compte existant
				 */
				if ( !$this->getData(['user', $userId])) {
					//Stockage de l'IP
					$this->setData([
						'blacklist',
						$userId,
						[
							'connectFail' => $this->getData(['blacklist',$userId,'connectFail']) + 1,
							'lastFail' => time(),
							'ip' => helper::getIp()
						]
					]);
					// Verrouillage des IP
					$ipBlackList = helper::arrayCollumn($this->getData(['blacklist']), 'ip');
					if ( $this->getData(['blacklist',$userId,'connectFail']) >= $this->getData(['config', 'connect', 'attempt'])
						AND in_array($this->getData(['blacklist',$userId,'ip']),$ipBlackList) ) {
						// Valeurs en sortie
						$this->addOutput([
							'notification' => 'Trop de tentatives, compte verrouillé',
							'redirect' => helper::baseUrl(),
							'state' => false
						]);
					} else {
						// Valeurs en sortie
						$this->addOutput([
							'notification' => 'Identifiant ou mot de passe incorrect'
						]);
					}
				/**
				 * Le compte existe
				 */
				} else 	{
					// Cas 4 : le délai de  blocage est  dépassé et le compte est au max - Réinitialiser
					if ($this->getData(['user',$userId,'connectTimeout'])  + $this->getData(['config', 'connect', 'timeout']) < time()
						AND $this->getData(['user',$userId,'connectFail']) === $this->getData(['config', 'connect', 'attempt']) ) {
						$this->setData(['user',$userId,'connectFail',0 ]);
						$this->setData(['user',$userId,'connectTimeout',0 ]);
					}
					// Check la présence des variables et contrôle du blocage du compte si valeurs dépassées
					// Vérification du mot de passe et du groupe
					if (
						( $this->getData(['user',$userId,'connectTimeout']) + $this->getData(['config', 'connect', 'timeout'])  ) < time()
						AND $this->getData(['user',$userId,'connectFail']) < $this->getData(['config', 'connect', 'attempt'])
						AND password_verify($this->getInput('userLoginPassword', helper::FILTER_STRING_SHORT, true), $this->getData(['user', $userId, 'password']))
						AND $this->getData(['user', $userId, 'group']) >= self::GROUP_MEMBER
					) {
						// Expiration
						$expire = $this->getInput('userLoginLongTime') ? strtotime("+1 year") : 0;
						$c = $this->getInput('userLoginLongTime', helper::FILTER_BOOLEAN) === true ? 'true' : 'false';
						setcookie('ZWII_USER_ID', $userId, $expire, helper::baseUrl(false, false)  , '', helper::isHttps(), true);
						setcookie('ZWII_USER_PASSWORD', $this->getData(['user', $userId, 'password']), $expire, helper::baseUrl(false, false), '', helper::isHttps(), true);
						setcookie('ZWII_USER_LONGTIME', $c, $expire, helper::baseUrl(false, false), '', helper::isHttps(), true);
						// Accès multiples avec le même compte
						$this->setData(['user',$userId,'accessCsrf',$_SESSION['csrf']]);
						// Valeurs en sortie lorsque le site est en maintenance et que l'utilisateur n'est pas administrateur
						if(
							$this->getData(['config', 'maintenance'])
							AND $this->getData(['user', $userId, 'group']) < self::GROUP_ADMIN
						) {
							$this->addOutput([
								'notification' => 'Seul un administrateur peut se connecter lors d\'une maintenance',
								'redirect' => helper::baseUrl(),
								'state' => false
							]);
						} else {
							// Valeurs en sortie
							$this->addOutput([
								'notification' => 'Connexion réussie',
								'redirect' => helper::baseUrl() . str_replace('_', '/', str_replace('__', '#', $this->getUrl(2))),
								'state' => true
							]);
						}
					// Sinon notification d'échec
					} else {
						$notification = 'Identifiant ou mot de passe incorrect';
						// Cas 1 le nombre de connexions est inférieur aux tentatives autorisées : incrément compteur d'échec
						if ($this->getData(['user',$userId,'connectFail']) < $this->getData(['config', 'connect', 'attempt'])) {
							$this->setData(['user',$userId,'connectFail',$this->getdata(['user',$userId,'connectFail']) + 1 ]);
						}
						// Cas 2 la limite du nombre de connexion est atteinte : placer le timer
						if ( $this->getdata(['user',$userId,'connectFail']) == $this->getData(['config', 'connect', 'attempt'])	) {
								$this->setData(['user',$userId,'connectTimeout', time()]);
						}
						// Cas 3 le délai de bloquage court
						if ($this->getData(['user',$userId,'connectTimeout'])  + $this->getData(['config', 'connect', 'timeout']) > time() ) {
							$notification = 'Trop de tentatives, accès bloqué durant ' . ($this->getData(['config', 'connect', 'timeout']) / 60) . ' minutes.';
						}
						// Journalisation
						$dataLog = strftime('%d/%m/%y',time()) . ';' . strftime('%R',time()) . ';' ;
						$dataLog .= helper::getIp() . ';';
						$dataLog .= $userId . ';' ;
						$dataLog .= $this->getUrl() .';' ;
						$dataLog .= 'échec de connexion' ;
						$dataLog .= PHP_EOL;
						if ($this->getData(['config','connect','log'])) {
							file_put_contents(self::DATA_DIR . 'journal.log', $dataLog, FILE_APPEND);
						}
						// Valeurs en sortie
						$this->addOutput([
							'notification' => $notification
						]);
					}
				}
			}
		}
		if (!empty($_COOKIE['ZWII_USER_ID'])) {
			self::$userId = $_COOKIE['ZWII_USER_ID'];
		}
		if (!empty($_COOKIE['ZWII_USER_LONGTIME'])) {
			self::$userLongtime = $_COOKIE['ZWII_USER_LONGTIME'] == 'true' ? true : false;
		}
		// Valeurs en sortie
		$this->addOutput([
			'display' => self::DISPLAY_LAYOUT_LIGHT,
			'title' => 'Connexion',
			'view' => 'login'
		]);
	}

	/**
	 * Déconnexion
	 */
	public function logout() {
		// Ne pas effacer l'identifiant mais seulement le mot de passe
		if (array_key_exists('ZWII_USER_LONGTIME',$_COOKIE)
			AND $_COOKIE['ZWII_USER_LONGTIME'] !== 'true' ) {
			helper::deleteCookie('ZWII_USER_ID');
			helper::deleteCookie('ZWII_USER_LONGTIME');
		}
		helper::deleteCookie('ZWII_USER_PASSWORD');
		session_destroy();
		// Valeurs en sortie
		$this->addOutput([
			'notification' => 'Déconnexion réussie',
			'redirect' => helper::baseUrl(false),
			'state' => true
		]);
	}

	/**
	 * Réinitialisation du mot de passe
	 */
	public function reset() {
		// Accès refusé
		if(
			// L'utilisateur n'existe pas
			$this->getData(['user', $this->getUrl(2)]) === null
			// Lien de réinitialisation trop vieux
			OR $this->getData(['user', $this->getUrl(2), 'forgot']) + 86400 < time()
			// Id unique incorrecte
			OR $this->getUrl(3) !== md5(json_encode($this->getData(['user', $this->getUrl(2)])))
		) {
			// Valeurs en sortie
			$this->addOutput([
				'access' => false
			]);
		}
		// Accès autorisé
		else {
			// Soumission du formulaire
			if($this->isPost()) {
				// Double vérification pour le mot de passe
				if($this->getInput('userResetNewPassword')) {
					// La confirmation ne correspond pas au mot de passe
					if($this->getInput('userResetNewPassword', helper::FILTER_STRING_SHORT, true) !== $this->getInput('userResetConfirmPassword', helper::FILTER_STRING_SHORT, true)) {
						$newPassword = $this->getData(['user', $this->getUrl(2), 'password']);
						self::$inputNotices['userResetConfirmPassword'] = 'Incorrect';
					}
					else {
						$newPassword = $this->getInput('userResetNewPassword', helper::FILTER_PASSWORD, true);
					}
					// Modifie le mot de passe
					$this->setData(['user', $this->getUrl(2), 'password', $newPassword]);
					// Réinitialise la date de la demande
					$this->setData(['user', $this->getUrl(2), 'forgot', 0]);
					// Valeurs en sortie
					$this->addOutput([
						'notification' => 'Nouveau mot de passe enregistré',
						'redirect' => helper::baseUrl() . 'user/login/' . str_replace('/', '_', $this->getUrl()),
						'state' => true
					]);
				}
			}
			// Valeurs en sortie
			$this->addOutput([
				'title' => 'Réinitialisation du mot de passe',
				'view' => 'reset'
			]);
		}
	}
}