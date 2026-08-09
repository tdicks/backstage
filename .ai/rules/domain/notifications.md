# Notifications

The app uses a catalog-driven notification system. New notification types should be added in a consistent way so they inherit defaults, admin controls, and user preference handling automatically.

## How a new notification type is created

1. Decide whether a new type is actually needed.
   - Reuse an existing notification type when the behavior is similar.
   - Avoid adding a new type for a one-off change if an existing one already covers it.

2. Add the type to the catalog.
   - Edit app/Support/NotificationTypeCatalog.php.
   - Define a new constant for the type key.
   - Add an entry in definitions() with:
     - label
     - description
     - category
     - defaults for enabled, popup, email, push, and text
     - optional admin_only or is_user_configurable flags

3. Let the system create admin settings for it.
   - The app will create admin-level settings automatically through NotificationSettings::ensureAdminSettingsExist().
   - This gives the system a default admin toggle for each channel before the type is used in production.

4. Emit the notification from the feature that triggers the event.
   - The sender should create the notification from the relevant controller, service, or model logic.
   - The notification should only be sent for meaningful events.
   - Do not notify the currently logged-in user about an action they just performed.

5. Respect user and admin preferences at send time.
   - Delivery should be checked via NotificationSettings::effectiveDeliveryPreferences($user, $type).
   - The system should suppress delivery when:
     - the admin has disabled the type,
     - the user has disabled a channel,
     - or the user is currently snoozed.

6. Add tests.
   - Cover the catalog entry.
   - Cover default preference behavior.
   - Cover the actual delivery path for the new type.
   - If the type is user-configurable, add profile/UI coverage as well.

## Conventions

- Use clear, specific type names.
- Keep descriptions short and action-oriented.
- Use the existing channel vocabulary: enabled, popup, email, push, text.
- If the notification is admin-only, set admin_only to true.
- If the notification should not be user-configurable, set is_user_configurable to false.

## Rule of thumb

If a user would reasonably want to know about an event, add a notification type. If the event is mostly internal noise, do not add one.

