// @flow
import {action, computed, observable} from 'mobx';
import type {StructureStrategyInterface, TreeItem} from '../types';

function flattenData(items: Array<TreeItem>, data: Array<Object> = []) {
    data.push(...items.map((item) => item.data));

    for (const item of items) {
        flattenData(item.children, data);
    }

    return data;
}

function findRecursive(items: Array<Object>, identifier: string | number): ?Object {
    for (const item of items) {
        // TODO do not hardcode id but use metdata instead
        if (item.data.id === identifier) {
            return item.data;
        }

        const data = findRecursive(item.children, identifier);
        if (data) {
            return data;
        }
    }
}

function removeRecursive(items: Array<TreeItem>, identifier: string | number): boolean {
    for (const index of items.keys()) {
        const item = items[index];
        if (item.data.id === identifier) {
            items.splice(index, 1);
            return true;
        }

        const removed = removeRecursive(item.children, identifier);

        if (removed && item.children.length === 0) {
            item.hasChildren = false;
            return true;
        }
    }

    return false;
}

function findChildrenForParentId(tree: Array<TreeItem>, parentId: ?string | number): ?Array<TreeItem> {
    if (parentId === undefined) {
        return tree;
    }

    for (let i = 0; i < tree.length; i++) {
        const item = tree[i];
        const {data, children} = item;
        if (parentId === data.id) {
            return children;
        }

        const childResult = findChildrenForParentId(children, parentId);
        if (childResult) {
            return childResult;
        }
    }
}

export default class TreeStructureStrategy implements StructureStrategyInterface {
    @observable data: Array<TreeItem> = [];

    @computed get visibleItems(): Array<Object> {
        return flattenData(this.data);
    }

    remove(identifier: string | number) {
        removeRecursive(this.data, identifier);
    }

    findById(id: string | number): ?Object {
        return findRecursive(this.data, id);
    }

    deactivate(id: ?string | number) {
        const children = findChildrenForParentId(this.data, id);
        if (children) {
            children.splice(0, children.length);
        }
    }

    addItem(item: Object, parentId: ?string | number): void {
        const children = findChildrenForParentId(this.data, parentId);

        if (!children) {
            throw new Error('Cannot add items to non-existing parentId "' + (parentId ? parentId : 'undefined') + '"!');
        }

        children.push({
            data: item,
            // TODO do not hardcode hasChildren but use metadata instead
            hasChildren: item.hasChildren,
            children: [],
        });

        if (item._embedded && Object.keys(item._embedded).length > 0) {
            const resourceKey = Object.keys(item._embedded)[0];
            const childItems = item._embedded[resourceKey];
            childItems.forEach((childItem) => this.addItem(childItem, item.id));
        }
    }

    @action clear(parentId: ?string | number) {
        const children = findChildrenForParentId(this.data, parentId);
        if (!children || children.length === 0) {
            return;
        }

        children.splice(0, children.length);
    }
}
