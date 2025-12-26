import json
import re

# Read the malformed JSON file
with open('IT_policy_Course_cleaned.json', 'r', encoding='utf-8') as f:
    lines = f.readlines()

print("Processing JSON file...")
print(f"Total lines: {len(lines)}")

# Extract all meaningful text content
text_parts = []

for line in lines:
    line = line.strip()

    # Skip empty lines and structural JSON characters
    if not line or line in ['{', '}', '']:
        continue

    # Remove leading/trailing quotes and commas
    line = re.sub(r'^["\s]*,?\s*', '', line)
    line = re.sub(r'\s*,?\s*["\s]*$', '', line)

    # Skip if it's just a key without meaningful content
    if re.match(r'^description\d*$', line.lower()):
        continue

    # Skip very short strings
    if len(line) < 5:
        continue

    # Clean up extra whitespace
    line = re.sub(r'\s+', ' ', line).strip()

    if line:
        text_parts.append(line)

print(f"Extracted {len(text_parts)} text parts")

# Combine all text into a single paragraph
combined_text = ' '.join(text_parts)

# Clean up any remaining formatting issues
combined_text = re.sub(r'\s+', ' ', combined_text).strip()

# Create the cleaned structure
cleaned_data = {
    "course_details": combined_text
}

# Write back to file
with open('IT_policy_Course_cleaned.json', 'w', encoding='utf-8') as f:
    json.dump(cleaned_data, f, indent=2, ensure_ascii=False)

print("✅ JSON file cleaned successfully!")
print(f"Combined text length: {len(combined_text)} characters")
print()
print("First 500 characters of combined text:")
print(combined_text[:500] + "...")
print()
print("Sample text parts extracted:")
for i, part in enumerate(text_parts[:3]):
    print(f"{i+1}: {part[:100]}...")