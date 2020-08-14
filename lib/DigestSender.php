<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Robin Appelman <robin@icewind.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Activity;

use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\Defaults;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use OCP\Util;

class DigestSender {
	const ACTIVITY_LIMIT = 20;

	private $config;
	private $data;
	private $userSettings;
	private $groupHelper;
	private $mailer;
	private $userManager;
	private $urlGenerator;
	private $defaults;
	private $l10nFactory;
	private $activityManager;
	private $dateFormatter;

	public function __construct(
		IConfig $config,
		Data $data,
		UserSettings $userSettings,
		GroupHelper $groupHelper,
		IMailer $mailer,
		IUserManager $userManager,
		IURLGenerator $urlGenerator,
		Defaults $defaults,
		IFactory $l10nFactory,
		IManager $activityManager,
		IDateTimeFormatter $dateTimeFormatter
	) {
		$this->config = $config;
		$this->data = $data;
		$this->userSettings = $userSettings;
		$this->groupHelper = $groupHelper;
		$this->mailer = $mailer;
		$this->userManager = $userManager;
		$this->urlGenerator = $urlGenerator;
		$this->defaults = $defaults;
		$this->l10nFactory = $l10nFactory;
		$this->activityManager = $activityManager;
		$this->dateFormatter = $dateTimeFormatter;
	}

	public function sendDigests(int $now) {
		$users = $this->getDigestUsers();
		$userLanguages = $this->config->getUserValueForUsers('core', 'lang', $users);
		$userTimezones = $this->config->getUserValueForUsers('core', 'timezone', $users);
		$defaultLanguage = $this->config->getSystemValue('default_language', 'en');
		$defaultTimeZone = date_default_timezone_get();

		foreach ($users as $user) {
			$language = (!empty($userLanguages[$user])) ? $userLanguages[$user] : $defaultLanguage;
			$timezone = (!empty($userTimezones[$user])) ? $userTimezones[$user] : $defaultTimeZone;
			$this->sendDigestForUser($user, $now, $timezone, $language);
		}
	}

	/**
	 * get all users who have activity digest enabled
	 *
	 * @return string[]
	 */
	private function getDigestUsers(): array {
		return $this->config->getUsersForUserValue('activity', 'notify_setting_activity_digest', 1);
	}

	private function getLastSendActivity(string $user, int $now): int {
		$lastSend = (int)$this->config->getUserValue($user, 'activity', 'activity_digest_last_send', 0);
		if ($lastSend > 0) {
			return $lastSend;
		}

		return $this->data->getFirstActivitySince($user, $now - (24 * 60 * 60));
	}

	public function sendDigestForUser(string $uid, int $now, string $timezone, string $language) {
		$l10n = $this->l10nFactory->get('activity', $language);
		$lastSend = $this->getLastSendActivity($uid, $now);
		$user = $this->userManager->get($uid);
		if ($lastSend === 0) {
			return;
		}

		['count' => $count, 'max' => $lastActivityId] = $this->data->getActivitySince($uid, $lastSend);
		if ($count == 0) {
			return;
		}

		/** @var IEvent[] $activities */
		$activities = $this->data->get(
			$this->groupHelper,
			$this->userSettings,
			$uid,
			$lastSend,
			self::ACTIVITY_LIMIT,
			'asc',
			'all',
			'',
			0,
			true
		);
		$skippedCount = max(0, $count - self::ACTIVITY_LIMIT);

		$template = $this->mailer->createEMailTemplate('activity.Notification', [
			'displayname' => $user->getDisplayName(),
			'url' => $this->urlGenerator->getAbsoluteURL('/'),
			'activityEvents' => $activities,
			'skippedCount' => $skippedCount,
		]);
		$template->setSubject($l10n->t('Daily activity digest for ' . $this->defaults->getName()));
		$template->addHeader();
		$template->addHeading($l10n->t('Hello %s', [$user->getDisplayName()]), $l10n->t('Hello %s,', [$user->getDisplayName()]));

		$homeLink = '<a href="' . $this->urlGenerator->getAbsoluteURL('/') . '">' . htmlspecialchars($this->defaults->getName()) . '</a>';
		$template->addBodyText(
			$l10n->t('There was some activity at %s', [$homeLink]),
			$l10n->t('There was some activity at %s', [$this->urlGenerator->getAbsoluteURL('/')])
		);

		foreach ($activities as $event) {
			$relativeDateTime = $this->dateFormatter->formatDateTimeRelativeDay(
				$event->getTimestamp(),
				'long',
				'short',
				new \DateTimeZone($timezone),
				$l10n
			);

			$template->addBodyListItem($this->getHTMLSubject($event), $relativeDateTime, $event->getIcon(), $event->getParsedSubject());
		}

		if ($skippedCount) {
			$template->addBodyListItem($l10n->n('and %n more ', 'and %n more ', $skippedCount));
		}

		$template->addFooter();

		$message = $this->mailer->createMessage();
		$message->setTo([$user->getEMailAddress() => $user->getDisplayName()]);
		$message->useTemplate($template);
		$message->setFrom([Util::getDefaultEmailAddress('no-reply') => $this->defaults->getName()]);

		try {
			$this->mailer->send($message);
			var_dump($lastActivityId);
			$this->config->setUserValue($user->getUID(), 'activity', 'activity_digest_last_send', $lastActivityId);
		} catch (\Exception $e) {
			var_dump($e->getMessage());
			return;
		}
	}

	/**
	 * @param IEvent $event
	 * @return string
	 */
	protected function getHTMLSubject(IEvent $event): string {
		if ($event->getRichSubject() === '') {
			return htmlspecialchars($event->getParsedSubject());
		}

		$placeholders = $replacements = [];
		foreach ($event->getRichSubjectParameters() as $placeholder => $parameter) {
			$placeholders[] = '{' . $placeholder . '}';

			if ($parameter['type'] === 'file') {
				$replacement = $parameter['path'];
			} else {
				$replacement = $parameter['name'];
			}

			if (isset($parameter['link'])) {
				$replacements[] = '<a href="' . $parameter['link'] . '">' . htmlspecialchars($replacement) . '</a>';
			} else {
				$replacements[] = '<strong>' . htmlspecialchars($replacement) . '</strong>';
			}
		}

		return str_replace($placeholders, $replacements, $event->getRichSubject());
	}
}
