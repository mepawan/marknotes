/**
 * Handle the PDF button from the content toolbar
 */
function fnPluginHTMLPDF() {

	/*<!-- build:debug -->*/
	if (marknotes.settings.debug) {
		console.log('      Plugin Page html - PDF');
	}
	/*<!-- endbuild -->*/

	// When the user has clicked on a note from the treeview, the jstree_init()
	// function (from jstree.js) has initialized the marknotes.note.file
	// variable to 'objNode.data.file' i.e. the data-file info of that
	// node and that info is set by the PHP listFiles task to the relative
	// filename of the note without any extension (so, f.i.
	// folder/documentation/marknotes and not the full file like
	// http://localhost/docs/folder/documentation/marknotes.html)
	if (marknotes.note.file == '') {
		// The user click on the Reveal button but should first select
		// a note in the treeview
		Noty({
			message: $.i18n('error_select_first'),
			type: 'error'
		});
	} else {
		var fname = marknotes.note.file + '.pdf';
		window.open(fname);
	}

	return true;
}
