#!/usr/bin/python

from sys import exc_info
from traceback import print_exception
from creds import DESIRED_CAP, success

GREEN = '\033[92m'
RED = '\033[91m'
END = '\033[0m'

def run_tests(obj, tests):
    passed = 0
    print
    for name in tests:
        func = getattr(obj, name)
        try:
            func()
            print '%s %sPASSED%s' % (name, GREEN, END)
            passed += 1
        except Exception:
            print '%s %sFAILED%s' % (name, RED, END)
            exc_type, exc_value, exc_traceback = exc_info()
            print_exception(exc_type, exc_value, exc_traceback)
    print
    print '%s%s of %s PASSED%s' % (GREEN, passed, len(tests), END)
    if (len(tests) > passed):
        print '%s%s of %s FAILED%s' % (RED, (len(tests) - passed), len(tests), END)
        obj.end()
        exit(1);
    success()
    print
    obj.end()
    return True

def get_tests(class_name):
    res = []
    for method in class_name.__dict__:
        if not method.startswith('__'):
            res.append(method)
    return res

def test_runner(class_name, tests=None):
    if not tests:
        tests = get_tests(class_name)
    if isinstance(DESIRED_CAP, list):
        for cap in DESIRED_CAP:
            run_tests(class_name(cap), tests)
    else:
        run_tests(class_name(), tests)
