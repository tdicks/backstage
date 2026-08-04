export class FeatureTourPrompt {
	constructor() {
		this.root = null;
		this.resolve = null;
	}

	show({ title, question, confirmLabel, cancelLabel, optOutLabel }) {
		if (this.root?.parentNode) {
			this.root.parentNode.removeChild(this.root);
		}

		return new Promise((resolve) => {
			this.resolve = resolve;
			this.root = document.createElement('div');
			this.root.className = 'feature-tour-prompt-root';

			const overlay = document.createElement('div');
			overlay.className = 'feature-tour-prompt-overlay';
			overlay.addEventListener('click', () => this.close('dismiss_prompt'));

			const frame = document.createElement('div');
			frame.className = 'feature-tour-prompt-frame';

			const dialog = document.createElement('section');
			dialog.className = 'feature-tour-prompt-dialog';
			dialog.setAttribute('role', 'dialog');
			dialog.setAttribute('aria-modal', 'true');
			dialog.setAttribute('aria-label', title);
			dialog.addEventListener('click', (event) => event.stopPropagation());

			const header = document.createElement('header');
			header.className = 'feature-tour-prompt-header';

			const headingWrap = document.createElement('div');
			const heading = document.createElement('h4');
			heading.className = 'feature-tour-prompt-title';
			heading.textContent = title;
			headingWrap.appendChild(heading);

			const closeButton = document.createElement('button');
			closeButton.type = 'button';
			closeButton.className = 'feature-tour-prompt-close';
			closeButton.setAttribute('aria-label', 'Close prompt');
			closeButton.textContent = '×';
			closeButton.addEventListener('click', () => this.close('dismiss_prompt'));

			header.append(headingWrap, closeButton);

			const body = document.createElement('div');
			body.className = 'feature-tour-prompt-body';
			const questionEl = document.createElement('p');
			questionEl.className = 'feature-tour-prompt-question';
			questionEl.textContent = question;
			body.appendChild(questionEl);

			const footer = document.createElement('footer');
			footer.className = 'feature-tour-prompt-footer';

			const leftActions = document.createElement('div');
			leftActions.className = 'feature-tour-prompt-actions-left';

			const rightActions = document.createElement('div');
			rightActions.className = 'feature-tour-prompt-actions-right';

			const optOutButton = document.createElement('button');
			optOutButton.type = 'button';
			optOutButton.className = 'feature-tour-button feature-tour-button-secondary';
			optOutButton.textContent = optOutLabel;
			optOutButton.addEventListener('click', () => this.close('opt_out'));

			const cancelButton = document.createElement('button');
			cancelButton.type = 'button';
			cancelButton.className = 'feature-tour-button feature-tour-button-secondary';
			cancelButton.textContent = cancelLabel;
			cancelButton.addEventListener('click', () => this.close('dismiss_prompt'));

			const confirmButton = document.createElement('button');
			confirmButton.type = 'button';
			confirmButton.className = 'feature-tour-button feature-tour-button-primary';
			confirmButton.textContent = confirmLabel;
			confirmButton.addEventListener('click', () => this.close('start'));

			leftActions.appendChild(optOutButton);
			rightActions.append(cancelButton, confirmButton);
			footer.append(leftActions, rightActions);

			dialog.append(header, body, footer);
			frame.appendChild(dialog);
			this.root.append(overlay, frame);
			document.body.appendChild(this.root);

			this.escapeHandler = (event) => {
				if (event.key === 'Escape') {
					this.close('dismiss_prompt');
				}
			};

			window.addEventListener('keydown', this.escapeHandler);
		});
	}

	close(decision) {
		if (this.escapeHandler) {
			window.removeEventListener('keydown', this.escapeHandler);
		}

		if (this.root?.parentNode) {
			this.root.parentNode.removeChild(this.root);
		}

		const resolver = this.resolve;
		this.resolve = null;
		this.root = null;

		if (resolver) {
			resolver(decision);
		}
	}
}

