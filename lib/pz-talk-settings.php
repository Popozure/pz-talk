<?php if (!function_exists("get_option")) die; ?>
<script type="text/javascript">
	function swap(a,b) {
		var s;
		s = document.getElementById("name-" + a).value;
 		document.getElementById("name-" + a).value = document.getElementById("name-" + b).value;
 		document.getElementById("name-" + b).value = s;
		s = document.getElementById("me-" + a).checked;
 		document.getElementById("me-" + a).checked = document.getElementById("me-" + b).checked;
 		document.getElementById("me-" + b).checked = s;
		s = document.getElementById("url-" + a).value;
 		document.getElementById("url-" + a).value = document.getElementById("url-" + b).value;
 		document.getElementById("url-" + b).value = s;
		s = document.getElementById("img-" + a).src;
 		document.getElementById("img-" + a).src = document.getElementById("img-" + b).src;
		document.getElementById("img-" + b).src = s;
	}

	function movePerson(from, to) {
		var i;
		if (from < to) {
			for (i = from; i < to; i++) {
				swap(i, i + 1);
			}
		} else {
			for (i = from; i > to; i--) {
				swap(i, i - 1);
			}
		}
	}

	document.addEventListener("DOMContentLoaded", function() {
		var table = document.getElementById("pz-talk-persons");
		var draggedIndex = null;
		var dragLabel = <?php echo wp_json_encode(__('Drag to reorder', $this->text_domain)); ?>;

		if (!table) {
			return;
		}

		function setOrderCell(row, index, draggable) {
			var cell = row.querySelector(".pz-talk-order-cell");
			var handle;
			var grip;
			var number;

			cell.textContent = "";
			if (!draggable) {
				cell.textContent = index;
				return;
			}

			handle = document.createElement("span");
			handle.className = "pz-talk-drag-handle";
			handle.draggable = false;
			handle.setAttribute("data-person-index", index);
			handle.title = dragLabel;
			handle.setAttribute("aria-label", dragLabel);
			grip = document.createElement("span");
			grip.className = "pz-talk-grip";
			grip.setAttribute("aria-hidden", "true");
			grip.innerHTML = "&#8942;&#8942;";
			number = document.createElement("span");
			number.textContent = index;
			handle.appendChild(grip);
			handle.appendChild(number);
			cell.appendChild(handle);
		}

		function appendEmptyPersonRow(row) {
			var index = parseInt(row.getAttribute("data-person-index"), 10);
			var nextIndex = index + 1;
			var newRow = row.cloneNode(true);
			var previousRow = row.previousElementSibling;
			var input;
			var buttons;

			if (previousRow && previousRow.hasAttribute("data-person-index")) {
				previousRow.querySelectorAll('input[name="up"]')[1].disabled = false;
			}
			row.classList.remove("pz-talk-new-person");
			setOrderCell(row, index, true);
			buttons = row.querySelectorAll('input[name="up"]');
			buttons[0].disabled = (index === 1);
			buttons[1].disabled = true;

			newRow.setAttribute("data-person-index", nextIndex);
			newRow.classList.add("pz-talk-new-person");
			setOrderCell(newRow, nextIndex, false);

			input = newRow.querySelector('input[id^="name-"]');
			input.id = "name-" + nextIndex;
			input.name = "properties[name-" + nextIndex + "]";
			input.value = "";
			input = newRow.querySelector('input[id^="me-"]');
			input.id = "me-" + nextIndex;
			input.name = "properties[me-" + nextIndex + "]";
			input.checked = false;
			input = newRow.querySelector('input[id^="url-"]');
			input.id = "url-" + nextIndex;
			input.name = "properties[url-" + nextIndex + "]";
			input.value = "";
			input.setAttribute("onInput", "document.getElementById('img-" + nextIndex + "').src = this.value;");
			newRow.querySelector('input[id^="mediaselect-"]').id = "mediaselect-" + nextIndex;
			input = newRow.querySelector('img[id^="img-"]');
			input.id = "img-" + nextIndex;
			input.src = "";

			buttons = newRow.querySelectorAll('input[name="up"]');
			buttons[0].id = "up-" + nextIndex;
			buttons[0].disabled = true;
			buttons[0].onclick = function() { swap(nextIndex - 1, nextIndex); };
			buttons[1].id = "down-" + nextIndex;
			buttons[1].disabled = true;
			buttons[1].onclick = function() { swap(nextIndex, nextIndex + 1); };
			row.parentNode.appendChild(newRow);
		}

		function addNextRowIfNeeded(event) {
			var row = event.target.closest("tr.pz-talk-new-person");
			if (!row || !event.target.matches('input[id^="name-"], input[id^="me-"], input[id^="url-"]')) {
				return;
			}
			appendEmptyPersonRow(row);
		}

		table.addEventListener("input", addNextRowIfNeeded);
		table.addEventListener("change", addNextRowIfNeeded);
		(function disableLastDownButton() {
			var emptyRow = table.querySelector("tr.pz-talk-new-person");
			var previousRow = emptyRow ? emptyRow.previousElementSibling : null;
			if (previousRow && previousRow.hasAttribute("data-person-index")) {
				previousRow.querySelectorAll('input[name="up"]')[1].disabled = true;
			}
		})();

		table.addEventListener('keydown', function(event) {
			if (!event.ctrlKey || event.altKey || event.metaKey || event.isComposing ||
				(event.key !== 'ArrowUp' && event.key !== 'ArrowDown')) {
				return;
			}
			var row = event.target.closest('tr[data-person-index]');
			if (!row || row.classList.contains('pz-talk-new-person')) { return; }
			event.preventDefault();
			var targetRow = event.key === 'ArrowUp' ? row.previousElementSibling : row.nextElementSibling;
			if (!targetRow || !targetRow.hasAttribute('data-person-index') || targetRow.classList.contains('pz-talk-new-person')) {
				return;
			}
			var controls = Array.from(row.querySelectorAll('input, [tabindex]'));
			var controlIndex = controls.indexOf(event.target);
			var start = event.target.selectionStart;
			var end = event.target.selectionEnd;
			swap(Number(row.dataset.personIndex), Number(targetRow.dataset.personIndex));
			var target = targetRow.querySelectorAll('input, [tabindex]')[controlIndex];
			if (target) {
				target.focus();
				if (typeof start === 'number' && typeof target.setSelectionRange === 'function') {
					target.setSelectionRange(start, end);
				}
			}
		});
		var press = null;
		var floating = null;
		var blockClick = false;
		var cancelContextMenu = false;
		function finish(cancel) {
			if (!press) { return; }
			clearTimeout(press.timer);
			document.body.classList.remove('pz-talk-sorting');
			if (floating) {
				if (!cancel) { movePerson(press.index, draggedIndex); }
				floating.remove();
				floating = null;
				blockClick = true;
				setTimeout(function() { blockClick = false; }, 0);
			}
			table.querySelectorAll('.pz-talk-dragging, .pz-talk-drop-target').forEach(function(row) {
				row.classList.remove('pz-talk-dragging', 'pz-talk-drop-target');
			});
			if (press.rows) {
				press.rows.forEach(function(item) {
					item.row.style.removeProperty('transform');
					item.row.style.removeProperty('background-color');
				});
			}
			press = null;
			draggedIndex = null;
		}
		table.addEventListener('pointerdown', function(event) {
			if (press && event.button === 2) {
				cancelContextMenu = true;
				event.preventDefault();
				finish(true);
				return;
			}
			var row = event.target.closest('tr[data-person-index]');
			if (press || !event.isPrimary || event.button !== 0 || !row || row.classList.contains('pz-talk-new-person')) { return; }
			press = { id: event.pointerId, index: Number(row.dataset.personIndex), x: event.clientX, y: event.clientY };
			function startDrag() {
				var rect = row.getBoundingClientRect();
				var clone = row.cloneNode(true);
				// Preserve the rendered row, including styles scoped to #pz-talk-persons.
				var originals = [row].concat(Array.from(row.querySelectorAll('*')));
				var copies = [clone].concat(Array.from(clone.querySelectorAll('*')));
				originals.forEach(function(original, index) {
					var computed = window.getComputedStyle(original);
					Array.from(computed).forEach(function(property) {
						copies[index].style.setProperty(property, computed.getPropertyValue(property));
					});
				});
				floating = document.createElement('table');
				floating.className = 'pz-talk-floating-row form-table widefat';
				clone.querySelectorAll('[id]').forEach(function(item) { item.removeAttribute('id'); });
				clone.querySelectorAll('input').forEach(function(item) { item.removeAttribute('name'); });
				Array.prototype.forEach.call(row.cells, function(cell, i) {
					clone.cells[i].style.boxSizing = 'border-box';
					clone.cells[i].style.width = cell.getBoundingClientRect().width + 'px';
				});
				floating.appendChild(document.createElement('tbody')).appendChild(clone);
				floating.style.width = rect.width + 'px';
				floating.style.left = rect.left + 'px';
				floating.style.top = rect.top + 'px';
				press.offset = press.y - rect.top;
				press.height = rect.height;
				press.lastDragTop = rect.top - 8;
				press.rows = Array.from(table.querySelectorAll('tr[data-person-index]:not(.pz-talk-new-person)')).map(function(item) {
					var bounds = item.getBoundingClientRect();
					var color = window.getComputedStyle(item).backgroundColor;
					if (color === 'rgba(0, 0, 0, 0)' || color === 'transparent') {
						color = window.getComputedStyle(table).backgroundColor;
					}
					return { row: item, index: Number(item.dataset.personIndex), top: bounds.top + window.scrollY, height: bounds.height, color: color };
				});
				document.body.appendChild(floating);
				row.classList.add('pz-talk-dragging');
				document.body.classList.add('pz-talk-sorting');
				draggedIndex = press.index;
				window.getSelection().removeAllRanges();
			}
			if (event.target.closest('.pz-talk-drag-handle')) {
				event.preventDefault();
				startDrag();
			} else {
				press.timer = setTimeout(startDrag, 500);
			}
		});
		document.addEventListener('pointermove', function(event) {
			if (!press || press.id !== event.pointerId) { return; }
			if (!floating) {
				if (Math.abs(event.clientX - press.x) > 8 || Math.abs(event.clientY - press.y) > 8) { finish(true); }
				return;
			}
			event.preventDefault();
			floating.style.top = (event.clientY - press.offset) + 'px';
			var floatingRect = floating.getBoundingClientRect();
			var direction = floatingRect.top - press.lastDragTop;
			press.lastDragTop = floatingRect.top;
			var order = press.rows.filter(function(item) { return item.index !== press.index; });
			var sourceRow = press.rows.find(function(item) { return item.index === press.index; });
			var position = draggedIndex - press.rows[0].index;
			order.splice(position, 0, sourceRow);
			var gapTop = press.rows[0].top - window.scrollY;
			for (var i = 0; i < position; i++) { gapTop += order[i].height; }
			if (direction < 0) {
				while (position > 0 && floatingRect.top < gapTop - order[position - 1].height / 2) {
					var previous = order[position - 1];
					order[position - 1] = sourceRow;
					order[position] = previous;
					gapTop -= previous.height;
					position--;
				}
			} else if (direction > 0) {
				while (position < order.length - 1 && floatingRect.bottom > gapTop + press.height + order[position + 1].height / 2) {
					var next = order[position + 1];
					order[position + 1] = sourceRow;
					order[position] = next;
					gapTop += next.height;
					position++;
				}
			}
			draggedIndex = press.rows[0].index + position;
			var nearest = press.rows.find(function(item) { return item.index === draggedIndex; });
			if (nearest) {
				draggedIndex = nearest.index;
				var source = press.rows.find(function(item) { return item.index === press.index; });
				press.rows.forEach(function(item) {
					var shift = 0;
					var previewIndex = item.index;
					if (item.index === press.index) {
						previewIndex = draggedIndex;
						shift = nearest.top - source.top;
						if (draggedIndex > press.index) { shift += nearest.height - press.height; }
					} else if (item.index > press.index && item.index <= draggedIndex) {
						previewIndex--;
						shift = -press.height;
					} else if (item.index < press.index && item.index >= draggedIndex) {
						previewIndex++;
						shift = press.height;
					}
					item.row.style.transform = 'translateY(' + shift + 'px)';
					var slot = press.rows.find(function(candidate) { return candidate.index === previewIndex; });
					item.row.style.backgroundColor = slot.color;
					if (item.index === press.index) {
						floating.querySelector('tr').style.backgroundColor = slot.color;
						floating.style.backgroundColor = slot.color;
					}
				});
			}
			if (event.clientY < 80) { window.scrollBy(0, -15); }
			else if (event.clientY > window.innerHeight - 60) { window.scrollBy(0, 15); }
		}, { passive: false });
		document.addEventListener('pointerup', function(event) {
			if (press && press.id === event.pointerId) { finish(false); }
		});
		document.addEventListener('pointercancel', function() { finish(true); });
		window.addEventListener('blur', function() { finish(true); });
		document.addEventListener('keydown', function(event) { if (event.key === 'Escape') { finish(true); } });
		table.addEventListener('click', function(event) {
			if (blockClick) { event.preventDefault(); event.stopImmediatePropagation(); }
		}, true);
		document.addEventListener('contextmenu', function(event) {
			if (press || blockClick || cancelContextMenu) {
				cancelContextMenu = false;
				event.preventDefault();
				finish(true);
			}
		});
		table.addEventListener('dragstart', function(event) { event.preventDefault(); });
	});
</script>
<div class="wrap">
	<h1 class="pz-talk-settings-title"><?php _e('Talk Settings', $this->text_domain); ?></h1>
	<hr class="wp-header-end">
	<div id="settings">
<?php if (isset($_GET['pz-talk-reset']) && $_GET['pz-talk-reset'] === '1') { ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings restored to defaults.', $this->text_domain); ?></p></div>
<?php } ?>
<?php
		if ( isset($_POST['properties'])) {
			check_admin_referer('pz_options');
			$this->options = $this->normalize_options($_POST['properties']);
			
			// セットされていないオプション項目をnullでセットする
			foreach ($this->defaults as $key => $value) {
				if (!isset($this->options[$key])) {
					$this->options[$key]	=	null;
				}
			}

			$i			=	1;
			while (isset($this->options['name-'.$i])) {
				if ($this->options['name-'.$i] == '')  {
					$j = $i + 1;
					while (isset($this->options['name-'.$j])) {
						if ($this->options['name-'.$j]) {
							$this->options['name-'.$i]	=	$this->options['name-'.$j];
							$this->options['me-'.$i]	=	$this->options['me-'.$j];
							$this->options['url-'.$i]	=	$this->options['url-'.$j];
							$this->options['name-'.$j]	=	'';
							$this->options['me-'.$j]	=	'';
							$this->options['url-'.$j]	=	'';
							break;
						}
						$j++;
					}
				}
				$i++;
			}
			
			$i			=	1;
			while (isset($this->options['name-'.$i])) {
				if ($this->options['name-'.$i] == '')  {
					unset($this->options['name-'.$i]);
					unset($this->options['me-'.$i]);
					unset($this->options['url-'.$i]);
				}
				$i++;
			}
			
			if (isset($this->options['initialize']) && $this->options['initialize'] == '1') {
				delete_option('Pz_Talk_options');
				$this->options	=	$this->normalize_options($this->defaults);
			}
			
			if (!$this->options['url-0']) {
				$this->options['url-0']		=	$this->plugin_dir_url.'img/unknown.png';
			}
			
			$result = true;
			if ($this->options['code1'] == '') {
				echo '<div class="error fade"><p><strong>'.__('Short code is not set.', $this->text_domain).'</strong></p></div>';
				$result = false;
			}
			
			$this->options['message-me-right']	=	'1';
			
			// サムネイルのキャッシュディレクトリの用意
			$wp_upload_dir			=	wp_upload_dir();
			$icon_dir			=	$wp_upload_dir['basedir'].'/'.$this->slug.'/icon/';
			$icon_url			=	$wp_upload_dir['baseurl'].'/'.$this->slug.'/icon/';
			if		(!is_dir($icon_dir)) {
				$icon_dir		=	$this->plugin_dir_path.'icon/';
				$icon_url		=	$this->plugin_dir_url .'icon/';
				if	(!is_dir($icon_dir)) {
					$icon_dir	=	null;
					$icon_url	=	null;
				}
			}
			$this->options['icon-dir']	= $icon_dir;
			$this->options['icon-url']	= $icon_url;
			
			// オプションの更新
			if ($result == true) {
				$result = update_option('Pz_Talk_options', $this->options);
				if ($result == true) {
					echo '<div class="updated fade"><p><strong>'.__('Changes saved.', $this->text_domain).'</strong></p></div>';
				} else {
					echo '<div class="error fade"><p><strong>'.__('Not changed.', $this->text_domain).'</strong></p></div>';
				}
			}
		}
		?>
		<form action="" method="post">
			<?php wp_nonce_field('pz_options'); ?>
			<div class="pz-talk-settings-save-anchor"><div class="pz-talk-settings-save"><?php submit_button(__('Save Changes'), 'primary', 'pz-talk-save-top', false); ?><small class="pz-talk-settings-shortcut">ALT+S</small></div></div>

			<h2 class="pz-talk-settings-heading"><?php _e('How to', $this->text_domain); ?></h2>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php _e("Plugin page", $this->text_domain); ?></th>
					<td><A href="https://popozure.info/pz-talk" target="_blank">https://popozure.info/pz-talk</A></td>
				</tr>
			</table>

			<h2 class="pz-talk-settings-heading"><?php _e('Persons', $this->text_domain); ?></h2>
			<table id="pz-talk-persons" class="form-table widefat striped">
				<colgroup>
					<col class="pz-talk-order-column" />
					<col class="pz-talk-name-column" />
					<col class="pz-talk-me-column" />
					<col class="pz-talk-url-cell" />
					<col class="pz-talk-image-column" />
					<col class="pz-talk-control-column" />
				</colgroup>
				<tr valign="top">
					<th scope="column"></th>
					<th scope="column"><?php _e('Name', $this->text_domain); ?></th>
					<th scope="column"><?php _e('Me', $this->text_domain); ?></th>
					<th scope="column" class="pz-talk-url-cell"><?php _e('Image URL', $this->text_domain); ?></th>
					<th scope="column"><?php _e('Image', $this->text_domain); ?></th>
					<th scope="column"></th>
				</tr>
				<tr valign="top" style="height: 98px;">
					<td></td>
					<td><?php _e('(unknown)', $this->text_domain); ?></td>
					<td></td>
					<td class="pz-talk-url-cell"><span class="pz-talk-url-fields"><input name="properties[url-0]" type="text" id="url-0" value="<?php echo esc_attr($this->options['url-0']) ?>" class="regular-text pz-talk-url-input" /><input type="button" id="mediaselect-0" name="mediaselect" value="<?php _e('Select', $this->text_domain) ?>..." /></span></td>
					<td><img src="<?php echo esc_url($this->options['url-0']); ?>" alt="" id="img-0" class="pz-talk-media-select-image" tabindex="0" role="button" aria-label="<?php echo esc_attr__('Select', $this->text_domain); ?>"></td>
					<td></td>
				</tr>
		<?php
		$i			=	0;
		do {
			$i++;
?>				<tr valign="top" style="height:98px;" data-person-index="<?php echo $i; ?>"<?php echo !isset($this->options['name-'.$i]) ? ' class="pz-talk-new-person"' : ''; ?>>
					<td class="pz-talk-order-cell"><?php if (isset($this->options['name-'.$i])) { ?><span class="pz-talk-drag-handle" draggable="false" data-person-index="<?php echo $i; ?>" title="<?php echo esc_attr__('Drag to reorder', $this->text_domain); ?>" aria-label="<?php echo esc_attr__('Drag to reorder', $this->text_domain); ?>"><span class="pz-talk-grip" aria-hidden="true">&#8942;&#8942;</span><span><?php echo $i; ?></span></span><?php } else { echo $i; } ?></td>
					<td><input name="properties[name-<?php echo $i; ?>]" type="text" id="name-<?php echo $i; ?>" value="<?php echo esc_attr(isset($this->options['name-'.$i]) ? $this->options['name-'.$i] : ''); ?>" class="regular-text pz-talk-name-input" /></td>
					<td><input name="properties[me-<?php echo $i; ?>]" type="checkbox" id="me-<?php echo $i; ?>" value="1" <?php echo (isset($this->options['me-'.$i]) ? 'checked="checked"' : '') ?> /></td>
					<td class="pz-talk-url-cell"><span class="pz-talk-url-fields"><input name="properties[url-<?php echo $i; ?>]" type="text" id="url-<?php echo $i; ?>" value="<?php echo esc_attr(isset($this->options['url-'.$i]) ? $this->options['url-'.$i] : ''); ?>" class="regular-text pz-talk-url-input" onInput="document.getElementById('img-<?php echo $i; ?>').src = this.value;" /><input type="button" id="mediaselect-<?php echo $i; ?>" name="mediaselect" value="<?php _e('Select', $this->text_domain); ?>..." /></span></td>
					<td><img src="<?php echo esc_url(isset($this->options['url-'.$i]) ? $this->options['url-'.$i] : ''); ?>" alt="" id="img-<?php echo $i; ?>" class="pz-talk-media-select-image" tabindex="0" role="button" aria-label="<?php echo esc_attr__('Select', $this->text_domain); ?>"></td>
					<td><input type="button" id="up-<?php echo $i; ?>" name="up" value="<?php _e('↑', $this->text_domain); ?>" onClick="swap(<?php echo ($i - 1).','.$i; ?>);" <?php if ($i == 1 || !isset($this->options['name-'.$i])) echo 'disabled=disabled '?>/><br><input type="button" id="up-<?php echo $i; ?>" name="up" value="<?php _e('↓', $this->text_domain); ?>" onClick="swap(<?php echo $i.','.($i + 1); ?>);" <?php if (!isset($this->options['name-'.$i])) echo 'disabled=disabled '?>/></td>
				</tr>
<?php	} while (isset($this->options['name-'.$i]));
		?>
			</table>
			<?php submit_button(); ?>

			<h2 class="pz-talk-settings-heading"><?php _e('Editor', $this->text_domain); ?></h2>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php _e('Add insert button', $this->text_domain); ?></th>
					<td><label><input name="properties[flg-edit-insert]" type="checkbox" id="check" value="1" <?php checked(isset($this->options['flg-edit-insert']) ? $this->options['flg-edit-insert'] : null, 1); ?> /><?php _e('Add insert button to visual editor.', $this->text_domain); ?></label></td>
				</tr>
			</table>
			<?php submit_button(); ?>

			<h2 class="pz-talk-settings-heading"><?php _e('Shortcode', $this->text_domain); ?></h2>
			<table class="form-table pz-talk-shortcode-table">
				<tr valign="top">
					<th scope="row"><?php _e('Talk', $this->text_domain); ?></th>
					<td><span class="pz-talk-shortcode-example">[<input name="properties[code1]" type="text" id="code1" value="<?php echo esc_attr($this->options['code1']); ?>" class="regular-text" style="width: 8em;" onKeyUp="document.getElementById('close1').innerText = document.getElementById('code1').value;" /> name="<span style="color: #aabbff;"><?php _e('Name', $this->text_domain); ?></span>"]<span style="color: #bbaaff;"><?php _e('Hello, world!!', $this->text_domain); ?></span>[/<span id="close1"><?php echo esc_attr($this->options['code1']); ?></span>]</span>
						<p><?php _e('Case-sensitive', $this->text_domain); ?></p></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e('Messenger', $this->text_domain); ?></th>
					<td><span class="pz-talk-shortcode-example">[<input name="properties[code2]" type="text" id="code2" value="<?php echo esc_attr($this->options['code2']); ?>" class="regular-text" style="width: 8em;" onKeyUp="document.getElementById('close2').innerText = document.getElementById('code2').value;" /> name="<span style="color: #aabbff;"><?php _e('Name', $this->text_domain); ?></span>"]<span style="color: #bbaaff;"><?php _e('Hello, world!!', $this->text_domain); ?></span>[/<span id="close2"><?php echo esc_attr($this->options['code2']); ?></span>]</span>
						<p><?php _e('Case-sensitive', $this->text_domain); ?></p></td>
				</tr>
			</table>
			<?php submit_button(); ?>

			<h2 class="pz-talk-settings-heading"><?php _e('Right justify', $this->text_domain); ?></h2>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php _e('Talk', $this->text_domain); ?></th>
					<td><input name="properties[talk-me-right]" type="checkbox" id="check" value="1" <?php checked(isset($this->options['talk-me-right']) ? $this->options['talk-me-right'] : null, 1); ?> /><?php _e('Right justify if it is named "Me".', $this->text_domain); ?></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e('Messenger', $this->text_domain); ?></th>
					<td><input name="properties[message-me-right]" type="checkbox" id="check" value="1" <?php checked(isset($this->options['message-me-right']) ? $this->options['message-me-right'] : null, 1); ?> checked="checked" disabled="disabled" /><?php _e('Right justify if it is named "Me".', $this->text_domain); ?></td>
				</tr>
			</table>
			<?php submit_button(); ?>

			<h2 class="pz-talk-settings-heading"><?php _e('Debug', $this->text_domain); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e('Admin Bar', $this->text_domain); ?></th>
					<td><label><input type="hidden" name="properties[flg-adminbar]" value="" /><input type="checkbox" name="properties[flg-adminbar]" value="1" <?php checked($this->options['flg-adminbar'], '1'); ?> /><?php esc_html_e('Display a menu in the admin bar.', $this->text_domain); ?></label></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e('Debug mode', $this->text_domain); ?></th>
					<td><label><input name="properties[flg-debug]" type="checkbox" id="check" value="1" <?php checked(isset($this->options['flg-debug']) ? $this->options['flg-debug'] : null, 1); ?> /><?php _e('The information required for investigation is output to the source.', $this->text_domain); ?></label></td>
				</tr>
			</table>
			<?php submit_button(); ?>

			<h2 class="pz-talk-settings-heading"><?php _e('etc', $this->text_domain); ?></h2>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php _e("Author's site", $this->text_domain); ?></th>
					<td><?php echo __('Popozure.', $this->text_domain).' ('.__("Poporon's PC daily diary", $this->text_domain).')'; ?><BR><A href="https://popozure.info" target="_blank">https://popozure.info</A></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e("Author's Twitter account", $this->text_domain); ?></th>
					<td><A href="https://twitter.com/popozure" target="_blank">https://twitter.com/popozure</A></td>
				</tr>
				<tr valign="top" style="display: none;">
					<th scope="row"><?php _e("Donation", $this->text_domain); ?></th>
					<td>https://www.amazon.co.jp/gp/registry/wishlist/2KIBQLC1VLA9X</td>
				</tr>
			</table>
		</form>
		<form id="pz-talk-reset-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;justify-content:flex-end;margin-top:24px">
			<input type="hidden" name="action" value="pz_talk_reset_settings">
			<?php wp_nonce_field('pz-talk-reset-settings'); ?>
			<?php submit_button(__('Restore default settings', $this->text_domain), 'secondary', 'pz-talk-reset', false); ?>
		</form>
		<script>
		document.getElementById('pz-talk-reset-form').addEventListener('submit', function(event) {
			if (!window.confirm(<?php echo wp_json_encode(__('Restore all settings to their defaults?', $this->text_domain)); ?>)) event.preventDefault();
		});
		</script>
	</div>
</div>
<?php
		function pz_TrimNum($val, $zero = 0 ) {
			$val		=	preg_replace('/[^0-9]/', '', $val) - 0;
			if ($val	==	0) {
				$val	=	$zero;
				$val	=	preg_replace('/[^0-9]/', '', $val) - 0;
			}
			return	$val;
		}
