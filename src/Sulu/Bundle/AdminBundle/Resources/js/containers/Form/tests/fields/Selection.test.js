// @flow
import React from 'react';
import {extendObservable as mockExtendObservable, observable} from 'mobx';
import {mount, shallow} from 'enzyme';
import Datagrid from '../../../Datagrid';
import Selection from '../../fields/Selection';
import FormInspector from '../../FormInspector';
import FormStore from '../../stores/FormStore';
import ResourceStore from '../../../../stores/ResourceStore';

jest.mock('../../../Datagrid', () => jest.fn(() => null));

jest.mock('../../../Datagrid/stores/DatagridStore',
    () => function(resourceKey, observableOptions = {}, options, initialSelectionIds) {
        this.resourceKey = resourceKey;
        this.locale = observableOptions.locale;
        this.initialSelectionIds = initialSelectionIds;
        this.dataLoading = true;

        mockExtendObservable(this, {
            selectionIds: [],
        });
    }
);

jest.mock('../../FormInspector', () => jest.fn(function(formStore) {
    this.id = formStore.id;
    this.resourceKey = formStore.resourceKey;
    this.locale = formStore.locale;
}));
jest.mock('../../stores/FormStore', () => jest.fn(function(resourceStore) {
    this.id = resourceStore.id;
    this.resourceKey = resourceStore.resourceKey;
    this.locale = resourceStore.locale;
}));
jest.mock('../../../../stores/ResourceStore', () => jest.fn(function(resourceKey, id, options) {
    this.id = id;
    this.resourceKey = resourceKey;
    this.locale = options ? options.locale : undefined;
}));

jest.mock('../../../../utils/Translator', () => ({
    translate: jest.fn(function(key) {
        return key;
    }),
}));

test('Should pass props correctly to selection component', () => {
    const changeSpy = jest.fn();
    const value = [1, 6, 8];

    const fieldTypeOptions = {
        default_type: 'overlay',
        resource_key: 'snippets',
        types: {
            overlay: {
                adapter: 'table',
                display_properties: ['id', 'title'],
                icon: '',
                label: 'sulu_snippet.selection_label',
                overlay_title: 'sulu_snippet.selection_overlay_title',
            },
        },
    };

    const locale = observable.box('en');

    const formInspector = new FormInspector(
        new FormStore(
            new ResourceStore('pages', 1, {locale})
        )
    );

    const selection = shallow(
        <Selection
            dataPath=""
            error={undefined}
            formInspector={formInspector}
            fieldTypeOptions={fieldTypeOptions}
            maxOccurs={undefined}
            minOccurs={undefined}
            onChange={changeSpy}
            onFinish={jest.fn()}
            schemaPath=""
            showAllErrors={false}
            types={undefined}
            value={value}
        />
    );

    expect(selection.find('Selection').props()).toEqual(expect.objectContaining({
        adapter: 'table',
        displayProperties: ['id', 'title'],
        label: 'sulu_snippet.selection_label',
        locale,
        onChange: changeSpy,
        resourceKey: 'snippets',
        overlayTitle: 'sulu_snippet.selection_overlay_title',
        value,
    }));
});

test('Should pass id of form as disabledId to overlay type to avoid assigning something to itself', () => {
    const fieldTypeOptions = {
        default_type: 'overlay',
        resource_key: 'pages',
        types: {
            overlay: {
                adapter: 'table',
            },
        },
    };

    const formInspector = new FormInspector(new FormStore(new ResourceStore('pages', 4)));

    const selection = shallow(
        <Selection
            dataPath=""
            error={undefined}
            formInspector={formInspector}
            fieldTypeOptions={fieldTypeOptions}
            maxOccurs={undefined}
            minOccurs={undefined}
            onChange={jest.fn()}
            onFinish={jest.fn()}
            schemaPath=""
            showAllErrors={false}
            types={undefined}
            value={undefined}
        />
    );

    expect(selection.find('Selection').prop('disabledIds')).toEqual([4]);
});

test('Should pass empty array if value is not given to overlay type', () => {
    const changeSpy = jest.fn();
    const fieldOptions = {
        default_type: 'overlay',
        resource_key: 'pages',
        types: {
            overlay: {
                adapter: 'column_list',
            },
        },
    };
    const formInspector = new FormInspector(new FormStore(new ResourceStore('snippets')));

    const selection = shallow(
        <Selection
            dataPath=""
            error={undefined}
            formInspector={formInspector}
            fieldTypeOptions={fieldOptions}
            maxOccurs={undefined}
            minOccurs={undefined}
            onChange={changeSpy}
            onFinish={jest.fn()}
            schemaPath=""
            showAllErrors={false}
            types={undefined}
            value={undefined}
        />
    );

    expect(selection.find('Selection').props()).toEqual(expect.objectContaining({
        adapter: 'column_list',
        onChange: changeSpy,
        resourceKey: 'pages',
        value: [],
    }));
});

test('Should throw an error if no "resource_key" option is passed in fieldOptions', () => {
    const formInspector = new FormInspector(new FormStore(new ResourceStore('snippets')));

    expect(() => shallow(
        <Selection
            dataPath=""
            error={undefined}
            formInspector={formInspector}
            fieldTypeOptions={{default_type: 'overlay'}}
            maxOccurs={undefined}
            minOccurs={undefined}
            onChange={jest.fn()}
            onFinish={jest.fn()}
            schemaPath=""
            showAllErrors={false}
            types={undefined}
            value={undefined}
        />
    )).toThrowError(/"resource_key"/);
});

test('Should throw an error if no "adapter" option is passed for overlay type in fieldTypeOptions', () => {
    const formInspector = new FormInspector(new FormStore(new ResourceStore('snippets')));
    const fieldTypeOptions = {
        default_type: 'overlay',
        resource_key: 'test',
        types: {
            overlay: {},
        },
    };

    expect(() => shallow(
        <Selection
            dataPath=""
            error={undefined}
            formInspector={formInspector}
            maxOccurs={undefined}
            minOccurs={undefined}
            onChange={jest.fn()}
            onFinish={jest.fn()}
            fieldTypeOptions={fieldTypeOptions}
            schemaPath=""
            showAllErrors={false}
            types={undefined}
            value={undefined}
        />
    )).toThrowError(/"adapter"/);
});

test('Should call the disposer for datagrid selections if unmounted', () => {
    const formInspector = new FormInspector(new FormStore(new ResourceStore('snippets')));
    const fieldTypeOptions = {
        default_type: 'datagrid',
        resource_key: 'test',
        types: {
            datagrid: {
                adapter: 'tree_table',
            },
        },
    };

    const selection = mount(
        <Selection
            dataPath=""
            error={undefined}
            formInspector={formInspector}
            maxOccurs={undefined}
            minOccurs={undefined}
            onChange={jest.fn()}
            onFinish={jest.fn()}
            fieldTypeOptions={fieldTypeOptions}
            schemaPath=""
            showAllErrors={false}
            types={undefined}
            value={undefined}
        />
    );

    const changeDatagridDisposerSpy = jest.fn();
    selection.instance().changeDatagridDisposer = changeDatagridDisposerSpy;

    selection.unmount();

    expect(changeDatagridDisposerSpy).toBeCalledWith();
});

test('Should pass props correctly to datagrid component', () => {
    const value = [1, 6, 8];

    const fieldTypeOptions = {
        default_type: 'datagrid',
        resource_key: 'snippets',
        types: {
            datagrid: {
                adapter: 'table',
            },
        },
    };

    const locale = observable.box('en');

    const formInspector = new FormInspector(
        new FormStore(
            new ResourceStore('pages', 1, {locale})
        )
    );

    const selection = shallow(
        <Selection
            dataPath=""
            error={undefined}
            formInspector={formInspector}
            fieldTypeOptions={fieldTypeOptions}
            maxOccurs={undefined}
            minOccurs={undefined}
            onChange={jest.fn()}
            onFinish={jest.fn()}
            schemaPath=""
            showAllErrors={false}
            types={undefined}
            value={value}
        />
    );

    expect(selection.instance().datagridStore.resourceKey).toEqual('snippets');
    expect(selection.instance().datagridStore.initialSelectionIds).toEqual(value);
    expect(selection.find(Datagrid).props()).toEqual(expect.objectContaining({
        adapters: ['table'],
        searchable: false,
    }));
});

test('Should call onChange and onFinish prop when datagrid selection changes', () => {
    const changeSpy = jest.fn();
    const finishSpy = jest.fn();

    const fieldTypeOptions = {
        default_type: 'datagrid',
        resource_key: 'snippets',
        types: {
            datagrid: {
                adapter: 'table',
            },
        },
    };

    const locale = observable.box('en');

    const formInspector = new FormInspector(
        new FormStore(
            new ResourceStore('pages', 1, {locale})
        )
    );

    const selection = shallow(
        <Selection
            dataPath=""
            error={undefined}
            formInspector={formInspector}
            fieldTypeOptions={fieldTypeOptions}
            maxOccurs={undefined}
            minOccurs={undefined}
            onChange={changeSpy}
            onFinish={finishSpy}
            schemaPath=""
            showAllErrors={false}
            types={undefined}
            value={undefined}
        />
    );

    selection.instance().datagridStore.dataLoading = false;
    selection.instance().datagridStore.selectionIds = [1, 5, 7];

    expect(changeSpy).toBeCalledWith([1, 5, 7]);
    expect(finishSpy).toBeCalledWith();
});

test('Should not call onChange and onFinish prop while datagrid is still loading', () => {
    const changeSpy = jest.fn();
    const finishSpy = jest.fn();

    const fieldTypeOptions = {
        default_type: 'datagrid',
        resource_key: 'snippets',
        types: {
            datagrid: {
                adapter: 'table',
            },
        },
    };

    const locale = observable.box('en');

    const formInspector = new FormInspector(
        new FormStore(
            new ResourceStore('pages', 1, {locale})
        )
    );

    const selection = shallow(
        <Selection
            dataPath=""
            error={undefined}
            formInspector={formInspector}
            fieldTypeOptions={fieldTypeOptions}
            maxOccurs={undefined}
            minOccurs={undefined}
            onChange={changeSpy}
            onFinish={finishSpy}
            schemaPath=""
            showAllErrors={false}
            types={undefined}
            value={undefined}
        />
    );

    selection.instance().datagridStore.selectionIds = [1, 5, 7];

    expect(changeSpy).not.toBeCalled();
    expect(finishSpy).not.toBeCalled();
});

test('Should pass props correctly to MultiAutoComplete component', () => {
    const value = [1, 6, 8];

    const fieldTypeOptions = {
        default_type: 'auto_complete',
        resource_key: 'snippets',
        types: {
            auto_complete: {
                display_property: 'name',
                filter_parameter: 'names',
                id_property: 'uuid',
                search_properties: ['name'],
            },
        },
    };

    const locale = observable.box('en');

    const formInspector = new FormInspector(
        new FormStore(
            new ResourceStore('pages', 1, {locale})
        )
    );

    const selection = shallow(
        <Selection
            dataPath=""
            error={undefined}
            formInspector={formInspector}
            fieldTypeOptions={fieldTypeOptions}
            maxOccurs={undefined}
            minOccurs={undefined}
            onChange={jest.fn()}
            onFinish={jest.fn()}
            schemaPath=""
            showAllErrors={false}
            types={undefined}
            value={value}
        />
    );

    expect(selection.find('MultiAutoComplete').props()).toEqual(expect.objectContaining({
        displayProperty: 'name',
        filterParameter: 'names',
        idProperty: 'uuid',
        locale,
        resourceKey: 'snippets',
        searchProperties: ['name'],
        value,
    }));
});
