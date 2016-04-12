#!/usr/bin/python

from sys import exc_info

GREEN = '\033[92m'
RED = '\033[91m'
END = '\033[0m'

def test_runner(obj, tests):
    print
    for name in tests:
        func = getattr(obj, name)
        try:
            func()
            print name+' '+GREEN+'PASSED'+END
        except Exception:
            print name+' '+RED+'FAILED'+END
            print exc_info()
    print
    obj.browser.end()
