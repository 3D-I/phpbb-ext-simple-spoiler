<?php

/**
 * Simple Spoiler Extension for phpBB.
 * @author Alfredo Ramos <alfredo.ramos@yandex.com>
 * @copyright 2017 Alfredo Ramos
 * @license GNU GPL-3.0+
 */

namespace alfredoramos\simplespoiler\includes;

use phpbb\db\driver\factory;

class helper {

	protected $db;
	protected $phpbb_root_path;
	protected $php_ext;
	protected $acp_bbcodes;

	/**
	 * Constructor of the helper class.
	 * @param	\php\db\driver\factory	$db
	 * @param	string					$phpbb_root_path
	 * @param	string					$php_ext
	 * @return	void
	 */
	public function __construct(factory $db, $phpbb_root_path, $php_ext) {
		$this->db = $db;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;

		if (!class_exists('acp_bbcodes')) {
			require_once $this->phpbb_root_path . 'includes/acp/acp_bbcodes.' . $this->php_ext;
		}

		$this->acp_bbcodes = new \acp_bbcodes;
	}

	/**
	 * Check whether BBCode already exists.
	 * @param	string		$bbcode_tag
	 * @return	int
	 */
	public function bbcode_exists($bbcode_tag = '') {
		if (empty($bbcode_tag)) {
			return -1;
		}

		$sql = 'SELECT bbcode_id
			FROM ' . BBCODES_TABLE . '
			WHERE bbcode_tag = "' . $this->db->sql_escape($bbcode_tag) .'"';
		$result = $this->db->sql_query($sql);
		$bbcode_id = (int) $this->db->sql_fetchfield('bbcode_id');
		$this->db->sql_freeresult($result);

		return $bbcode_id;
	}

	/**
	 * Calculate the ID for the BBCode that is about to be installed.
	 * @return	int
	 */
	public function bbcode_id() {
		$sql = 'SELECT MAX(bbcode_id) as last_id
			FROM ' . BBCODES_TABLE;
		$result = $this->db->sql_query($sql);
		$last_id = (int) $this->db->sql_fetchfield('last_id');
		$this->db->sql_freeresult($result);
		$bbcode_id = $last_id + 1;

		if ($bbcode_id <= NUM_CORE_BBCODES) {
			$bbcode_id = NUM_CORE_BBCODES + 1;
		}

		return $bbcode_id;
	}

	/**
	 * Install the new BBCode adding it in the database or updating it
	 * if it already exists.
	 * @return	void
	 */
	public function install_bbcode() {
		$data = $this->bbcode_data();

		$data['bbcode_id'] = $this->bbcode_id();

		$data = array_replace(
			$data,
			$this->acp_bbcodes->build_regexp(
				$data['bbcode_match'],
				$data['bbcode_tpl']
			)
		);

		if ($bbcode_id = $this->bbcode_exists($data['bbcode_tag'])) {
			$this->update_bbcode($bbcode_id, $data);
		} else {
			$this->add_bbcode($data);
		}
	}

	/**
	 * Add the BBCode in the database.
	 * @param	array			$data
	 * @return	boolean|void
	 */
	public function add_bbcode($data = []) {
		if (empty($data) ||
			(!empty($data['bbcode_id']) && $data['bbcode_id'] > BBCODE_LIMIT)
		) {
			return false;
		}

		$sql = 'INSERT INTO ' . BBCODES_TABLE . ' ' .
			$this->db->sql_build_array('INSERT', $data);
		$this->db->sql_query($sql);

	}

	/**
	 * Update BBCode data if it already exists.
	 * @param	int				$bbcode_id
	 * @param	array			$data
	 * @return	boolean|void
	 */
	public function update_bbcode($bbcode_id = 0, $data = []) {
		if (empty($bbcode_id) || empty($data)) {
			return false;
		}

		$bbcode_id = (int) $bbcode_id;
		unset($data['bbcode_id']);

		$sql = 'UPDATE ' . BBCODES_TABLE . '
			SET ' . $this->db->sql_build_array('UPDATE', $data) . '
			WHERE bbcode_id = ' . $bbcode_id;
		$this->db->sql_query($sql);
	}

	/**
	 * Uninstall the BBCode from the database.
	 * @return	boolean|void
	 */
	public function uninstall_bbcode() {
		$data = $this->bbcode_data();
		unset($data['bbcode_id']);
		$bbcode_id = $this->bbcode_exists($data['bbcode_tag']);

		if ($bbcode_id <= NUM_CORE_BBCODES) {
			return false;
		}

		$sql = 'DELETE FROM ' . BBCODES_TABLE . '
			WHERE bbcode_id = ' . $bbcode_id;
		$this->db->sql_query($sql);
	}

	/**
	 * BBCode data used in the migration files.
	 * @return	array
	 */
	public function bbcode_data() {
		return [
			'bbcode_tag'	=> 'spoiler=',
			'bbcode_match'	=> '[spoiler={TEXT2;optional}]{TEXT1}[/spoiler]',
			'bbcode_tpl'	=> '<xsl:choose>'.
				'<xsl:when test="@spoiler">'.
				'<div class="spoiler">'.
				'<div class="spoiler-header spoiler-trigger">'.
				'<span class="spoiler-title">{TEXT2}</span>'.
				'<span class="spoiler-status">{L_SPOILER_SHOW}</span>'.
				'</div>'.
				'<div class="spoiler-body">{TEXT1}</div>'.
				'</div>'.
				'</xsl:when>'.
				'<xsl:otherwise>'.
				'<div class="spoiler">'.
				'<div class="spoiler-header spoiler-trigger">'.
				'<span class="spoiler-title">{L_SPOILER}</span>'.
				'<span class="spoiler-status">{L_SPOILER_SHOW}</span>'.
				'</div>'.
				'<div class="spoiler-body">{TEXT1}</div>'.
				'</div>'.
				'</xsl:otherwise>'.
				'</xsl:choose>',
			'bbcode_helpline'	=> 'SPOILER_HELPLINE',
			'display_on_posting'	=> 0
		];
	}
}
