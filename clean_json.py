import json

def remove_keys_recursive(obj, keys_to_remove):
    """Recursively remove specified keys from a dictionary or list"""
    if isinstance(obj, dict):
        # Remove the specified keys
        for key in keys_to_remove:
            obj.pop(key, None)
        # Recursively process all values
        for value in obj.values():
            remove_keys_recursive(value, keys_to_remove)
    elif isinstance(obj, list):
        # Recursively process all items in the list
        for item in obj:
            remove_keys_recursive(item, keys_to_remove)

def remove_widgets_by_type(obj):
    """Remove widget keys from columns where type is not 'text' or 'quiz'"""
    if isinstance(obj, dict):
        # Check if this is a column with a widget
        if 'columns' in obj:
            for column in obj['columns']:
                if 'widget' in column:
                    widget_type = column['widget'].get('type')
                    if widget_type not in ['text', 'quiz']:
                        del column['widget']
        
        # Recursively process all values
        for value in obj.values():
            remove_widgets_by_type(value)
    elif isinstance(obj, list):
        # Recursively process all items in the list
        for item in obj:
            remove_widgets_by_type(item)

# Read the JSON file
with open('VALID_COURSE.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

# Remove all 'id' and 'widget_counts' keys
remove_keys_recursive(data, ['id', 'widget_counts'])

# Remove widgets that are not 'text' or 'quiz' type
remove_widgets_by_type(data)

# Write the cleaned JSON back
with open('VALID_COURSE.json', 'w', encoding='utf-8') as f:
    json.dump(data, f, indent=4, ensure_ascii=False)

print("JSON cleaned successfully!")
