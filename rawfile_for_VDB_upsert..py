# # # # -------- SAMPLE TEXT -------------------

import json


def load_course_details():
    with open('course_sample_textfile.txt', 'r') as file:
        data = file.read()
    return data

text=load_course_details()
print(text)