jQuery(document).ready(function ($) {
	let csvHeaders = [];
	let csvData = [];

	// Scan template for placeholders
	$('#scan_placeholders').on('click', function () {
		const templateId = $('#template_page').val();
		if (!templateId) {
			alert('Please select a template page first');
			return;
		}

		$.ajax({
			url: fsBulkPageGenerator.ajax_url,
			type: 'POST',
			data: {
				action: 'get_template_placeholders',
				nonce: fsBulkPageGenerator.nonce,
				template_id: templateId,
			},
			success: function (response) {
				if (response.success) {
					$('#mapping_section').show();
					createMappingFields(response.data.placeholders);

					// Log debug information
					console.log('Template scanning debug info:', response.data.debug);

					if (response.data.placeholders.length === 0) {
						alert(
							'No placeholders found in the template. Please ensure your placeholders are in the format {{placeholder_name}}'
						);
					}
				} else {
					alert('Error: ' + response.data);
				}
			},
			error: function (xhr, status, error) {
				console.error('AJAX Error:', error);
				alert('Error scanning template: ' + error);
			},
		});
	});

	// Handle CSV file upload
	$('#csv_file').on('change', function (e) {
		const file = e.target.files[0];
		if (!file) return;

		const reader = new FileReader();
		reader.onload = function (e) {
			const text = e.target.result;
			parseCSV(text);
			populateColumnMappers();
			populateSlugOptions();
			$('#preview_section').show();
		};
		reader.readAsText(file);
	});

	// Create mapping fields for placeholders
	function createMappingFields(placeholders) {
		const container = $('#mapping_fields');
		container.empty();

		// Add mapping fields for all placeholders
		placeholders.forEach(placeholder => {
			const fieldHtml = `
                <div class="mapping-field" style="margin-bottom: 10px;">
                    <label style="display: inline-block; width: 200px;">
                        {{${placeholder}}}:
                    </label>
                    <select class="column-mapper" data-placeholder="${placeholder}">
                        <option value="">Select CSV column...</option>
                    </select>
                </div>
            `;
			container.append(fieldHtml);
		});
	}

	// Parse CSV data
	function parseCSV(text) {
		const rows = CSVToArray(text);
		csvHeaders = rows[0];
		csvData = [];

		for (let i = 1; i < rows.length; i++) {
			const obj = {};
			for (let j = 0; j < csvHeaders.length; j++) {
				obj[csvHeaders[j]] = rows[i][j];
			}
			csvData.push(obj);
		}
	}

	// Populate column mapping dropdowns
	function populateColumnMappers() {
		$('.column-mapper').each(function () {
			const select = $(this);
			select.empty();
			select.append('<option value="">Select CSV column...</option>');

			csvHeaders.forEach(header => {
				select.append(`<option value="${header}">${header}</option>`);
			});
		});
	}

	// Populate slug options dropdown
	function populateSlugOptions() {
		const slugSelect = $('#slug_column');
		slugSelect.empty();
		slugSelect.append('<option value="">Select CSV column...</option>');

		csvHeaders.forEach(header => {
			slugSelect.append(`<option value="${header}">${header}</option>`);
		});
	}

	// Handle template page selection
	$('#template_page').on('change', function () {
		if ($(this).val()) {
			// Refresh parent page options excluding the selected template
			const templateId = $(this).val();
			$('#parent_page option').show();
			$('#parent_page option[value="' + templateId + '"]').hide();
		}
	});

	// Preview mapping
	$('#preview_mapping').on('click', function () {
		if (csvData.length === 0) {
			alert('Please upload a CSV file first');
			return;
		}

		const mapping = {};
		$('.column-mapper').each(function () {
			const placeholder = $(this).data('placeholder');
			const column = $(this).val();
			if (column) {
				mapping[placeholder] = column;
			}
		});

		$.ajax({
			url: fsBulkPageGenerator.ajax_url,
			type: 'POST',
			data: {
				action: 'preview_mapping',
				nonce: fsBulkPageGenerator.nonce,
				template_id: $('#template_page').val(),
				mapping: mapping,
				sample_data: csvData[0],
			},
			success: function (response) {
				if (response.success) {
					$('#preview_content').html(response.data.preview);
				} else {
					alert('Error: ' + response.data);
				}
			},
		});
	});

	// Generate pages
	$('#generate_pages').on('click', function () {
		if (!confirm('Are you sure you want to generate pages for all CSV rows?')) {
			return;
		}

		const mapping = {};
		$('.column-mapper').each(function () {
			const placeholder = $(this).data('placeholder');
			const column = $(this).val();
			if (column) {
				mapping[placeholder] = column;
			}
		});

		const slugSettings = {
			column: $('#slug_column').val(),
			parent_id: $('#parent_page').val(),
		};

		$('#progress_area').show();
		const totalRows = csvData.length;
		let processedRows = 0;

		processBatch(0);

		function processBatch(startIndex) {
			const batch = csvData.slice(startIndex, startIndex + 10);
			if (batch.length === 0) {
				return;
			}

			$.ajax({
				url: fsBulkPageGenerator.ajax_url,
				type: 'POST',
				data: {
					action: 'process_csv',
					nonce: fsBulkPageGenerator.nonce,
					template_id: $('#template_page').val(),
					mapping: mapping,
					csv_data: batch,
					slug_settings: slugSettings,
					yoast_settings: {
						import_meta_title: $('input[name="import_meta_title"]:checked').val(),
						import_meta_desc: $('input[name="import_meta_desc"]:checked').val(),
					},
				},
				success: function (response) {
					processedRows += batch.length;
					const progress = (processedRows / totalRows) * 100;

					$('.progress-bar').css('width', progress + '%');
					$('#progress_text').text(
						`Processed ${processedRows} of ${totalRows} rows`
					);

					if (processedRows < totalRows) {
						processBatch(startIndex + 10);
					} else {
						alert('All pages have been generated!');
						$('#progress_area').hide();
					}
				},
				error: function (xhr, status, error) {
					console.error('AJAX Error:', {
						status: status,
						error: error,
						response: xhr.responseText,
						xhr: xhr,
					});

					// Log the data that was being sent
					console.log('Data being sent:', {
						action: 'process_csv',
						nonce: fsBulkPageGenerator.nonce,
						template_id: $('#template_page').val(),
						mapping: mapping,
						csv_data: batch,
						slug_settings: slugSettings,
					});

					let errorMessage = 'Error processing batch: ';
					if (xhr.responseJSON && xhr.responseJSON.data) {
						errorMessage += xhr.responseJSON.data;
					} else if (xhr.responseText) {
						errorMessage += xhr.responseText;
					} else {
						errorMessage += error;
					}

					alert(errorMessage);
					$('#progress_area').hide();
				},
			});
		}
	});

	// CSV to Array function
	function CSVToArray(strData, strDelimiter) {
		strDelimiter = strDelimiter || ',';
		let objPattern = new RegExp(
			'(\\' +
				strDelimiter +
				'|\\r?\\n|\\r|^)' +
				'(?:"([^"]*(?:""[^"]*)*)"|' +
				'([^"\\' +
				strDelimiter +
				'\\r\\n]*))',
			'gi'
		);
		let arrData = [[]];
		let arrMatches = null;
		while ((arrMatches = objPattern.exec(strData))) {
			let strMatchedDelimiter = arrMatches[1];
			if (strMatchedDelimiter.length && strMatchedDelimiter !== strDelimiter) {
				arrData.push([]);
			}
			let strMatchedValue;
			if (arrMatches[2]) {
				strMatchedValue = arrMatches[2].replace(new RegExp('""', 'g'), '"');
			} else {
				strMatchedValue = arrMatches[3];
			}
			arrData[arrData.length - 1].push(strMatchedValue);
		}
		return arrData;
	}
});
