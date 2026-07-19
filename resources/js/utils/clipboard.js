export async function copyShareLink(url) {
	if (navigator.clipboard && window.isSecureContext) {
		await navigator.clipboard.writeText(url);

		return;
	}

	const input = document.createElement('textarea');
	input.value = url;
	input.setAttribute('readonly', '');
	input.style.position = 'fixed';
	input.style.opacity = '0';
	document.body.appendChild(input);
	input.select();
	document.execCommand('copy');
	document.body.removeChild(input);
}
